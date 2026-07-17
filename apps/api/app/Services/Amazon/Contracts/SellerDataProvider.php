<?php

namespace App\Services\Amazon\Contracts;

use App\Models\ChannelAccount;
use App\Models\Marketplace;

interface SellerDataProvider
{
    public function authorizationUrl(string $state, Marketplace $marketplace, bool $draft = false): string;

    public function exchangeAuthorizationCode(string $code): array;

    public function marketplaceParticipations(ChannelAccount $account): array;

    public function importListings(ChannelAccount $account, string $marketplaceId): iterable;

    public function getCatalogItem(ChannelAccount $account, string $marketplaceId, string $asin): array;

    public function getListingItem(ChannelAccount $account, string $marketplaceId, string $sku): array;

    public function getProductTypeDefinition(ChannelAccount $account, string $marketplaceId, string $productType): array;

    public function previewListingPatch(ChannelAccount $account, string $marketplaceId, string $sku, string $productType, array $patches): array;

    public function publishListingPatch(ChannelAccount $account, string $marketplaceId, string $sku, string $productType, array $patches): array;
}
