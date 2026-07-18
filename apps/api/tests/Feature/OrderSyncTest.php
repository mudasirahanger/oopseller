<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\ChannelAccount;
use App\Models\Client;
use App\Models\Listing;
use App\Models\MetricSnapshot;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\Channels\ChannelOrderSyncService;
use App\Services\Channels\Contracts\ChannelProvider;
use App\Services\Channels\MeeshoChannelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_sync_persists_orders_and_metric_snapshots(): void
    {
        [$account] = $this->meeshoAccount();
        $this->bindStubProvider();

        $run = app(ChannelOrderSyncService::class)->syncOrders($account);

        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->processed);

        $order = Order::where('external_order_id', 'ORD-1')->firstOrFail();
        $this->assertSame('shipped', $order->status);
        $this->assertSame(2, $order->units);
        $this->assertSame(998.0, (float) $order->total);

        // Cancelled orders are stored but excluded from revenue aggregates.
        $snapshot = MetricSnapshot::where('marketplace_id', 'meesho')->firstOrFail();
        $this->assertSame(998.0, (float) $snapshot->revenue);
        $this->assertSame(1, (int) $snapshot->orders);
        $this->assertSame(2, (int) $snapshot->units);

        $this->assertNotNull($account->fresh()->metadata['orders_synced_at'] ?? null);
    }

    public function test_order_sync_is_idempotent_on_reruns(): void
    {
        [$account] = $this->meeshoAccount();
        $this->bindStubProvider();

        app(ChannelOrderSyncService::class)->syncOrders($account);
        app(ChannelOrderSyncService::class)->syncOrders($account, from: now()->subDays(30)->toImmutable());

        $this->assertSame(2, Order::count());
        $this->assertSame(1, MetricSnapshot::count());
    }

    public function test_orders_endpoints_are_organization_scoped_with_summary_math(): void
    {
        [$account, , $headers] = $this->meeshoAccount();
        $this->bindStubProvider();
        app(ChannelOrderSyncService::class)->syncOrders($account);

        $list = $this->withHeaders($headers)->getJson('/api/v1/orders')->assertOk();
        $this->assertSame(2, $list->json('total'));

        $summary = $this->withHeaders($headers)->getJson('/api/v1/orders/summary')->assertOk()->json('data');
        $this->assertSame(998.0, (float) $summary['totals']['revenue']);
        $this->assertSame(1, $summary['totals']['orders']);
        $this->assertSame(1, $summary['totals']['cancelled_or_returned']);
        $this->assertSame(998.0, (float) $summary['totals']['average_order_value']);
        $this->assertSame('meesho', $summary['by_platform'][0]['platform']);
        $this->assertSame('Cotton Kurti', $summary['top_products'][0]['name']);

        // A different organization must not see these orders.
        $otherOrganization = Organization::create(['name' => 'Other', 'slug' => 'other-'.str()->random(5), 'timezone' => 'UTC', 'currency' => 'INR']);
        $otherUser = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password123', 'current_organization_id' => $otherOrganization->id]);
        $otherOrganization->users()->attach($otherUser->id, ['role' => 'owner']);
        app('auth')->forgetGuards();

        $foreign = $this->withHeaders([
            'Authorization' => 'Bearer '.$otherUser->createToken('t')->plainTextToken,
            'X-Organization-Id' => (string) $otherOrganization->id,
        ])->getJson('/api/v1/orders')->assertOk();
        $this->assertSame(0, $foreign->json('total'));
    }

    public function test_order_sync_endpoint_queues_and_dedupes(): void
    {
        [$account, , $headers] = $this->meeshoAccount();
        Queue::fake();

        $first = $this->withHeaders($headers)->postJson("/api/v1/integrations/channel-accounts/{$account->id}/sync-orders")->assertAccepted();
        $second = $this->withHeaders($headers)->postJson("/api/v1/integrations/channel-accounts/{$account->id}/sync-orders")->assertAccepted();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
    }

    private function bindStubProvider(): void
    {
        $this->app->bind(MeeshoChannelProvider::class, fn () => new class implements ChannelProvider
        {
            public function platform(): Platform
            {
                return Platform::Meesho;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function authorizationUrl(string $state, array $options = []): ?string
            {
                return null;
            }

            public function exchangeCode(string $code): array
            {
                return [];
            }

            public function syncListings(ChannelAccount $account, array $options = []): iterable
            {
                return [];
            }

            public function getOrders(ChannelAccount $account, \DateTimeInterface $from, \DateTimeInterface $to, array $options = []): iterable
            {
                yield [
                    'external_order_id' => 'ORD-1',
                    'status' => 'shipped',
                    'order_date' => now()->subDay()->toIso8601String(),
                    'fulfillment_type' => 'platform_fulfilled',
                    'marketplace_id' => 'meesho_in',
                    'items' => [
                        ['external_id' => 'MP-1001', 'sku' => 'SKU-1', 'name' => 'Cotton Kurti', 'quantity' => 2, 'unit_price' => 499.0, 'total' => 998.0],
                    ],
                    'units' => 2,
                    'subtotal' => 998.0,
                    'tax' => 0.0,
                    'shipping' => 0.0,
                    'total' => 998.0,
                    'currency' => 'INR',
                    'customer_city' => 'Srinagar',
                    'customer_state' => 'Jammu and Kashmir',
                    'customer_pincode' => '190001',
                    'raw' => [],
                ];
                yield [
                    'external_order_id' => 'ORD-2',
                    'status' => 'cancelled',
                    'order_date' => now()->subDay()->toIso8601String(),
                    'items' => [],
                    'units' => 1,
                    'total' => 250.0,
                    'currency' => 'INR',
                    'raw' => [],
                ];
            }

            public function updateListing(ChannelAccount $account, Listing $listing, array $patches, array $options = []): array
            {
                return [];
            }

            public function getInventory(ChannelAccount $account, array $options = []): iterable
            {
                return [];
            }
        });
    }

    private function meeshoAccount(): array
    {
        $organization = Organization::create(['name' => 'Orders Agency', 'slug' => 'orders-agency-'.str()->random(5), 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $user = User::create(['name' => 'Owner', 'email' => 'orders-owner@example.com', 'password' => 'password123', 'current_organization_id' => $organization->id]);
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $client = Client::create(['organization_id' => $organization->id, 'name' => 'Orders Client', 'slug' => 'orders-client-'.str()->random(4), 'status' => 'active']);
        $account = ChannelAccount::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'platform' => 'meesho',
            'account_identifier' => 'SUP-9',
            'name' => 'Meesho SUP-9',
            'region' => 'in',
            'status' => 'active',
            'credentials' => ['supplier_id' => 'SUP-9', 'api_key' => 'k', 'api_secret' => 's'],
        ]);

        return [$account, $organization, [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $organization->id,
        ]];
    }
}
