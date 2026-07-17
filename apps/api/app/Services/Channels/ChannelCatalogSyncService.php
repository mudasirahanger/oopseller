<?php

namespace App\Services\Channels;

use App\Models\ChannelAccount;
use App\Models\ChannelSyncRun;
use App\Models\Listing;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Throwable;

// Persists the normalized listing rows yielded by non-Amazon ChannelProviders
// into products + listings. Amazon keeps its richer AmazonCatalogSyncService.
final class ChannelCatalogSyncService
{
    public function __construct(private readonly ChannelManager $channels) {}

    public function syncListings(ChannelAccount $account, ?ChannelSyncRun $run = null): ChannelSyncRun
    {
        $run ??= ChannelSyncRun::create([
            'organization_id' => $account->organization_id,
            'client_id' => $account->client_id,
            'platform' => $account->platform,
            'channel_account_id' => $account->id,
            'marketplace_id' => $account->platform.'_in',
            'type' => 'listings',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $run->update(['status' => 'running', 'started_at' => $run->started_at ?: now(), 'error' => null]);

        try {
            $provider = $this->channels->provider($account->platform);

            foreach ($provider->syncListings($account) as $item) {
                try {
                    if ($this->persist($account, $item)) {
                        $run->increment('processed');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $run->increment('failed');
                }
            }

            $run->update(['status' => 'completed', 'finished_at' => now()]);
            $account->update(['last_synced_at' => now(), 'last_sync_error' => null]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            $account->update(['last_sync_error' => $exception->getMessage()]);
            throw $exception;
        }

        return $run->fresh();
    }

    /**
     * @param  array<string, mixed>  $item  normalized listing row from a ChannelProvider
     */
    private function persist(ChannelAccount $account, array $item): bool
    {
        $externalId = trim((string) ($item['external_id'] ?? ''));

        if ($externalId === '') {
            return false;
        }

        return DB::transaction(function () use ($account, $item, $externalId): bool {
            $product = Product::withTrashed()->firstOrNew([
                'client_id' => $account->client_id,
                'platform' => $account->platform,
                'external_id' => $externalId,
            ]);
            if ($product->trashed()) {
                $product->restore();
            }
            $product->fill([
                'organization_id' => $account->organization_id,
                'channel_account_id' => $account->id,
                'sku' => $item['sku'] ?? $product->sku,
                'name' => (string) ($item['name'] ?? $product->name ?? 'Untitled product'),
                'image_url' => $item['image_url'] ?? $product->image_url,
                'status' => 'active',
                'source' => $account->platform,
                'last_imported_at' => now(),
            ])->save();

            $marketplaceCode = (string) ($item['marketplace_code'] ?? $account->platform.'_in');
            $listing = Listing::withTrashed()->firstOrNew([
                'product_id' => $product->id,
                'marketplace_id' => $marketplaceCode,
            ]);
            if ($listing->trashed()) {
                $listing->restore();
            }
            $listing->fill([
                'organization_id' => $account->organization_id,
                'client_id' => $account->client_id,
                'platform' => $account->platform,
                'channel_account_id' => $account->id,
                'seller_sku' => $item['sku'] ?? $listing->seller_sku,
                'title' => (string) ($item['name'] ?? $listing->title ?? ''),
                'status' => (string) ($item['status'] ?? 'active'),
                'raw_payload' => $item['raw'] ?? null,
                'attributes' => array_filter([
                    'price' => $item['price'] ?? null,
                    'currency' => $item['currency'] ?? null,
                ], fn ($value) => $value !== null),
                'last_imported_at' => now(),
                'last_sync_error' => null,
            ])->save();

            return true;
        });
    }
}
