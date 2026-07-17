<?php

namespace App\Services\Channels;

use App\Enums\Platform;
use App\Models\ChannelAccount;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Services\Amazon\Contracts\SellerDataProvider;
use App\Services\Channels\Contracts\ChannelProvider;
use App\Services\Channels\Exceptions\UnsupportedChannelOperation;
use DateTimeInterface;

// Adapts the existing SP-API integration to the generic ChannelProvider
// contract. Amazon-specific callers may keep using SellerDataProvider directly;
// platform-agnostic code (sync orchestration, integration hub) goes through
// this adapter.
final class AmazonChannelProvider implements ChannelProvider
{
    public function __construct(private readonly SellerDataProvider $amazon) {}

    public function platform(): Platform
    {
        return Platform::Amazon;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.amazon.lwa_client_id'))
            && filled(config('services.amazon.lwa_client_secret'))
            && filled(config('services.amazon.application_id'))
            && filled(config('services.amazon.redirect_uri'));
    }

    public function authorizationUrl(string $state, array $options = []): ?string
    {
        $marketplace = $options['marketplace'] ?? null;
        abort_unless($marketplace instanceof Marketplace, 422, 'Amazon authorization requires a marketplace.');

        return $this->amazon->authorizationUrl(
            $state,
            $marketplace,
            (bool) ($options['draft'] ?? config('services.amazon.authorization_draft')),
        );
    }

    public function exchangeCode(string $code): array
    {
        return $this->amazon->exchangeAuthorizationCode($code);
    }

    public function syncListings(ChannelAccount $account, array $options = []): iterable
    {
        $marketplaceId = (string) ($options['marketplace_id'] ?? '');
        abort_unless($marketplaceId !== '', 422, 'Amazon listing sync requires a marketplace_id.');

        return $this->amazon->importListings($account, $marketplaceId);
    }

    public function getOrders(ChannelAccount $account, DateTimeInterface $from, DateTimeInterface $to, array $options = []): iterable
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'order sync');
    }

    public function updateListing(ChannelAccount $account, Listing $listing, array $patches, array $options = []): array
    {
        abort_unless(filled($listing->seller_sku), 422, 'The listing has no seller SKU.');
        $productType = (string) ($options['product_type'] ?? '');
        abort_unless($productType !== '', 422, 'Amazon listing updates require a product_type.');

        return $this->amazon->publishListingPatch(
            $account,
            $listing->marketplace_id,
            $listing->seller_sku,
            $productType,
            $patches,
        );
    }

    public function getInventory(ChannelAccount $account, array $options = []): iterable
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'inventory sync');
    }
}
