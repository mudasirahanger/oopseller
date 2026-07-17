<?php

namespace App\Services\Amazon;

use App\Models\ChannelAccount;
use App\Models\ChannelSyncRun;
use App\Models\Listing;
use App\Models\Product;
use App\Services\Amazon\Contracts\SellerDataProvider;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AmazonCatalogSyncService
{
    public function __construct(
        private readonly SellerDataProvider $provider,
        private readonly AmazonDataMapper $mapper,
    ) {}

    public function syncListings(ChannelAccount $account, string $marketplaceId, ?ChannelSyncRun $run = null): ChannelSyncRun
    {
        $run ??= ChannelSyncRun::create([
            'organization_id' => $account->organization_id,
            'client_id' => $account->client_id,
            'channel_account_id' => $account->id,
            'marketplace_id' => $marketplaceId,
            'type' => 'listings',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $run->update(['status' => 'running', 'started_at' => $run->started_at ?: now(), 'error' => null]);

        try {
            foreach ($this->provider->importListings($account, $marketplaceId) as $item) {
                try {
                    $this->persistListing($account, $marketplaceId, $item);
                    $run->increment('processed');
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

    public function importCatalogItem(ChannelAccount $account, string $marketplaceId, string $asin, ?string $sellerSku = null): Product
    {
        $catalog = $this->mapper->catalog(
            $this->provider->getCatalogItem($account, $marketplaceId, $asin),
            $marketplaceId,
        );

        $product = $this->upsertProduct($account, $asin, [
            'sku' => $sellerSku,
            'name' => $catalog['name'],
            'product_type' => $catalog['product_type'],
            'image_url' => $catalog['image_url'],
            'metadata' => $catalog['metadata'],
        ]);

        if ($sellerSku) {
            $listingPayload = $this->provider->getListingItem($account, $marketplaceId, $sellerSku);
            $this->persistListing($account, $marketplaceId, $listingPayload, $product);
        }

        return $product->fresh(['client', 'channelAccount', 'listings']);
    }

    public function refreshProduct(Product $product, string $marketplaceId, ?string $sellerSku = null): Product
    {
        $account = $product->amazonAccount;
        abort_unless($account && $account->status === 'active', 422, 'The product is not connected to an active Amazon seller account.');
        abort_unless($account->marketplaces()->where('amazon_marketplace_id', $marketplaceId)->wherePivot('enabled', true)->exists(), 422, 'This marketplace is not enabled for the Amazon account.');

        return $this->importCatalogItem($account, $marketplaceId, $product->asin, $sellerSku ?: $product->sku);
    }

    private function upsertProduct(ChannelAccount $account, string $asin, array $values): Product
    {
        $product = Product::withTrashed()->firstOrNew([
            'client_id' => $account->client_id,
            'asin' => strtoupper($asin),
        ]);
        if ($product->trashed()) {
            $product->restore();
        }
        $product->fill([
            ...array_filter($values, fn ($value) => $value !== null),
            'organization_id' => $account->organization_id,
            'channel_account_id' => $account->id,
            'external_id' => strtoupper($asin),
            'status' => 'active',
            'source' => 'amazon',
            'last_imported_at' => now(),
        ])->save();

        return $product;
    }

    private function persistListing(ChannelAccount $account, string $marketplaceId, array $item, ?Product $knownProduct = null): ?Listing
    {
        $mapped = $this->mapper->listing($item, $marketplaceId);
        $asin = $mapped['asin'] ?: $knownProduct?->asin;

        if (! $asin) {
            return null;
        }

        return DB::transaction(function () use ($account, $marketplaceId, $mapped, $asin, $knownProduct): Listing {
            $product = $knownProduct ?: $this->upsertProduct($account, $asin, [
                'sku' => $mapped['sku'],
                'name' => $mapped['name'],
                'product_type' => $mapped['product_type'],
                'image_url' => $mapped['image_url'],
                'metadata' => $mapped['product_metadata'],
            ]);

            $product->update(array_filter([
                'channel_account_id' => $account->id,
                'sku' => $mapped['sku'],
                'name' => $mapped['name'],
                'product_type' => $mapped['product_type'],
                'image_url' => $mapped['image_url'],
                'source' => 'amazon',
                'last_imported_at' => now(),
                'metadata' => $mapped['product_metadata'],
            ], fn ($value) => $value !== null));

            $listing = Listing::withTrashed()->firstOrNew([
                'product_id' => $product->id,
                'marketplace_id' => $marketplaceId,
            ]);
            if ($listing->trashed()) {
                $listing->restore();
            }
            $listing->fill([
                ...$mapped['listing'],
                'organization_id' => $account->organization_id,
                'client_id' => $account->client_id,
                'channel_account_id' => $account->id,
                'last_imported_at' => now(),
                'last_sync_error' => null,
            ])->save();

            return $listing;
        });
    }
}
