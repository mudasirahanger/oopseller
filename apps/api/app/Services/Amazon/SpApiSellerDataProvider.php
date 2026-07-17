<?php

namespace App\Services\Amazon;

use App\Models\ChannelAccount;
use App\Models\Marketplace;
use App\Services\Amazon\Contracts\SellerDataProvider;

final class SpApiSellerDataProvider implements SellerDataProvider
{
    public function __construct(
        private readonly AmazonConfiguration $configuration,
        private readonly AmazonLwaClient $lwa,
        private readonly AmazonSpApiClient $client,
    ) {}

    public function authorizationUrl(string $state, Marketplace $marketplace, bool $draft = false): string
    {
        $this->configuration->assertConfigured();

        $query = [
            'application_id' => config('services.amazon.application_id'),
            'state' => $state,
            'redirect_uri' => config('services.amazon.redirect_uri'),
        ];

        if ($draft) {
            $query['version'] = 'beta';
        }

        return rtrim($this->configuration->sellerCentralUrl($marketplace), '/')
            .'/apps/authorize/consent?'
            .http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        return $this->lwa->exchangeAuthorizationCode($code);
    }

    public function marketplaceParticipations(ChannelAccount $account): array
    {
        return $this->client->get($account, '/sellers/v1/marketplaceParticipations')['payload'] ?? [];
    }

    public function importListings(ChannelAccount $account, string $marketplaceId): iterable
    {
        $pageToken = null;

        do {
            $response = $this->client->get(
                $account,
                '/listings/2021-08-01/items/'.rawurlencode((string) $account->account_identifier),
                [
                    'marketplaceIds' => $marketplaceId,
                    'includedData' => ['summaries', 'attributes', 'issues', 'offers', 'fulfillmentAvailability', 'relationships', 'productTypes'],
                    'pageSize' => 20,
                    'pageToken' => $pageToken,
                    'sortBy' => 'lastUpdatedDate',
                    'sortOrder' => 'DESC',
                ],
            );

            foreach ($response['items'] ?? [] as $item) {
                yield $item;
            }

            $pageToken = data_get($response, 'pagination.nextToken');
        } while (filled($pageToken));
    }

    public function getCatalogItem(ChannelAccount $account, string $marketplaceId, string $asin): array
    {
        return $this->client->get(
            $account,
            '/catalog/2022-04-01/items/'.rawurlencode($asin),
            [
                'marketplaceIds' => $marketplaceId,
                'includedData' => ['attributes', 'classifications', 'dimensions', 'identifiers', 'images', 'productTypes', 'relationships', 'salesRanks', 'summaries'],
                'locale' => $this->localeForMarketplace($marketplaceId),
            ],
        );
    }

    public function getListingItem(ChannelAccount $account, string $marketplaceId, string $sku): array
    {
        return $this->client->get(
            $account,
            '/listings/2021-08-01/items/'.rawurlencode((string) $account->account_identifier).'/'.rawurlencode($sku),
            [
                'marketplaceIds' => $marketplaceId,
                'includedData' => ['summaries', 'attributes', 'issues', 'offers', 'fulfillmentAvailability', 'relationships', 'productTypes'],
                'issueLocale' => $this->localeForMarketplace($marketplaceId),
            ],
        );
    }

    public function getProductTypeDefinition(ChannelAccount $account, string $marketplaceId, string $productType): array
    {
        return $this->client->get(
            $account,
            '/definitions/2020-09-01/productTypes/'.rawurlencode($productType),
            [
                'sellerId' => $account->account_identifier,
                'marketplaceIds' => $marketplaceId,
                'productTypeVersion' => 'LATEST',
                'requirements' => 'LISTING',
                'requirementsEnforced' => 'NOT_ENFORCED',
                'locale' => $this->localeForMarketplace($marketplaceId),
            ],
        );
    }

    public function previewListingPatch(ChannelAccount $account, string $marketplaceId, string $sku, string $productType, array $patches): array
    {
        return $this->listingPatch($account, $marketplaceId, $sku, $productType, $patches, true);
    }

    public function publishListingPatch(ChannelAccount $account, string $marketplaceId, string $sku, string $productType, array $patches): array
    {
        return $this->listingPatch($account, $marketplaceId, $sku, $productType, $patches, false);
    }

    private function listingPatch(ChannelAccount $account, string $marketplaceId, string $sku, string $productType, array $patches, bool $preview): array
    {
        return $this->client->patch(
            $account,
            '/listings/2021-08-01/items/'.rawurlencode((string) $account->account_identifier).'/'.rawurlencode($sku),
            [
                'marketplaceIds' => $marketplaceId,
                'includedData' => $preview ? ['issues', 'identifiers'] : ['issues'],
                'issueLocale' => $this->localeForMarketplace($marketplaceId),
                'mode' => $preview ? 'VALIDATION_PREVIEW' : null,
            ],
            [
                'productType' => $productType,
                'patches' => $patches,
            ],
        );
    }

    private function localeForMarketplace(string $marketplaceId): string
    {
        return match ($marketplaceId) {
            'A21TJRUUN4KGV' => 'en_IN',
            'A1F83G8C2ARO7P' => 'en_GB',
            'ATVPDKIKX0DER' => 'en_US',
            'A2VIGQ35RCS4UG', 'A17E79C6D8DWNP' => 'en_AE',
            default => 'en_US',
        };
    }
}
