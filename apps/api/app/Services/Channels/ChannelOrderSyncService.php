<?php

namespace App\Services\Channels;

use App\Models\ChannelAccount;
use App\Models\ChannelSyncRun;
use App\Models\MetricSnapshot;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Throwable;

// Pulls orders from a channel provider, upserts them, and rebuilds the daily
// MetricSnapshot aggregates that power the dashboard and monthly reports.
final class ChannelOrderSyncService
{
    private const REVENUE_STATUSES = ['confirmed', 'shipped', 'delivered'];

    public function __construct(private readonly ChannelManager $channels) {}

    public function syncOrders(ChannelAccount $account, ?ChannelSyncRun $run = null, ?CarbonImmutable $from = null): ChannelSyncRun
    {
        $run ??= ChannelSyncRun::create([
            'organization_id' => $account->organization_id,
            'client_id' => $account->client_id,
            'platform' => $account->platform,
            'channel_account_id' => $account->id,
            'marketplace_id' => $account->platform,
            'type' => 'orders',
            'status' => 'running',
            'started_at' => now(),
        ]);
        $run->update(['status' => 'running', 'started_at' => $run->started_at ?: now(), 'error' => null]);

        // Incremental: continue from the last completed order sync, with a
        // 24h overlap for late status updates; first sync covers 30 days.
        $from ??= ($account->metadata['orders_synced_at'] ?? null)
            ? CarbonImmutable::parse($account->metadata['orders_synced_at'])->subDay()
            : CarbonImmutable::now()->subDays(30);
        $to = CarbonImmutable::now()->subMinutes(5);

        $touchedDates = [];

        try {
            $provider = $this->channels->provider($account->platform);

            foreach ($provider->getOrders($account, $from, $to) as $item) {
                try {
                    $order = $this->persist($account, $item);
                    if ($order) {
                        $touchedDates[$order->order_date->toDateString()] = true;
                        $run->increment('processed');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $run->increment('failed');
                }
            }

            $this->rebuildMetricSnapshots($account, array_keys($touchedDates));

            $run->update(['status' => 'completed', 'finished_at' => now()]);
            $account->update([
                'last_synced_at' => now(),
                'last_sync_error' => null,
                'metadata' => [...($account->metadata ?? []), 'orders_synced_at' => $to->toIso8601String()],
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            $account->update(['last_sync_error' => $exception->getMessage()]);
            throw $exception;
        }

        return $run->fresh();
    }

    /** @param array<string, mixed> $item normalized order from a ChannelProvider */
    private function persist(ChannelAccount $account, array $item): ?Order
    {
        $externalOrderId = trim((string) ($item['external_order_id'] ?? ''));

        if ($externalOrderId === '') {
            return null;
        }

        return Order::updateOrCreate(
            [
                'platform' => $account->platform,
                'external_order_id' => $externalOrderId,
                'client_id' => $account->client_id,
            ],
            [
                'organization_id' => $account->organization_id,
                'channel_account_id' => $account->id,
                'status' => in_array($item['status'] ?? '', Order::STATUSES, true) ? $item['status'] : 'pending',
                'order_date' => Carbon::parse((string) ($item['order_date'] ?? now())),
                'fulfillment_type' => $item['fulfillment_type'] ?? null,
                'marketplace_id' => $item['marketplace_id'] ?? null,
                'items' => $item['items'] ?? [],
                'units' => (int) ($item['units'] ?? 0),
                'subtotal' => (float) ($item['subtotal'] ?? 0),
                'tax' => (float) ($item['tax'] ?? 0),
                'shipping' => (float) ($item['shipping'] ?? 0),
                'total' => (float) ($item['total'] ?? 0),
                'currency' => strtoupper((string) ($item['currency'] ?? 'INR')),
                'customer_city' => $item['customer_city'] ?? null,
                'customer_state' => $item['customer_state'] ?? null,
                'customer_pincode' => $item['customer_pincode'] ?? null,
                'metadata' => ['raw' => $item['raw'] ?? null],
            ],
        );
    }

    /** @param array<int, string> $dates */
    private function rebuildMetricSnapshots(ChannelAccount $account, array $dates): void
    {
        foreach ($dates as $date) {
            $aggregate = Order::query()
                ->where('client_id', $account->client_id)
                ->where('platform', $account->platform)
                ->whereDate('order_date', $date)
                ->whereIn('status', self::REVENUE_STATUSES)
                ->selectRaw('COALESCE(SUM(total),0) as revenue, COUNT(*) as orders, COALESCE(SUM(units),0) as units')
                ->first();

            MetricSnapshot::updateOrCreate(
                [
                    'client_id' => $account->client_id,
                    'product_id' => null,
                    'marketplace_id' => $account->platform,
                    'metric_date' => Carbon::parse($date)->startOfDay(),
                ],
                [
                    'organization_id' => $account->organization_id,
                    'revenue' => (float) ($aggregate->revenue ?? 0),
                    'orders' => (int) ($aggregate->orders ?? 0),
                    'units' => (int) ($aggregate->units ?? 0),
                    'recorded_at' => now(),
                ],
            );
        }
    }
}
