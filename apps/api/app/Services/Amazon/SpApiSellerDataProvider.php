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
        $region = Marketplace::where('amazon_marketplace_id', $marketplaceId)->value('region') ?? $account->region;
        $isSandbox = (bool) ($account->metadata['sandbox'] ?? config('services.amazon.sandbox'));

        do {
            try {
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
                    $region
                );
            } catch (AmazonSpApiException $exception) {
                if ($isSandbox && str_contains($exception->getMessage(), 'Could not match input arguments')) {
                    break;
                }
                throw $exception;
            }

            foreach ($response['items'] ?? [] as $item) {
                yield $item;
            }

            $pageToken = data_get($response, 'pagination.nextToken');
        } while (filled($pageToken));
    }

    public function importOrders(ChannelAccount $account, array $marketplaceIds, \DateTimeInterface $updatedAfter, ?\DateTimeInterface $updatedBefore = null): iterable
    {
        $nextToken = null;
        $region = Marketplace::where('amazon_marketplace_id', reset($marketplaceIds))->value('region') ?? $account->region;
        $isSandbox = (bool) ($account->metadata['sandbox'] ?? config('services.amazon.sandbox'));

        do {
            $query = $nextToken
                ? ['NextToken' => $nextToken]
                : array_filter([
                    'MarketplaceIds' => $marketplaceIds,
                    'LastUpdatedAfter' => $updatedAfter->format('Y-m-d\TH:i:s\Z'),
                    'LastUpdatedBefore' => $updatedBefore?->format('Y-m-d\TH:i:s\Z'),
                    'MaxResultsPerPage' => 100,
                ]);

            try {
                $payload = $this->client->get($account, '/orders/v0/orders', $query, $region)['payload'] ?? [];
            } catch (AmazonSpApiException $exception) {
                if ($isSandbox && str_contains($exception->getMessage(), 'Could not match input arguments')) {
                    break;
                }
                throw $exception;
            }

            foreach ($payload['Orders'] ?? [] as $order) {
                $order['OrderItems'] = $this->orderItems($account, (string) $order['AmazonOrderId'], $region);

                yield $order;
            }

            $nextToken = $payload['NextToken'] ?? null;
        } while (filled($nextToken));
    }

    /** @return array<int, array<string, mixed>> */
    private function orderItems(ChannelAccount $account, string $amazonOrderId, ?string $region = null): array
    {
        $items = [];
        $nextToken = null;

        do {
            $payload = $this->client->get(
                $account,
                '/orders/v0/orders/'.rawurlencode($amazonOrderId).'/orderItems',
                array_filter(['NextToken' => $nextToken]),
                $region
            )['payload'] ?? [];

            $items = [...$items, ...($payload['OrderItems'] ?? [])];
            $nextToken = $payload['NextToken'] ?? null;
        } while (filled($nextToken));

        return $items;
    }

    public function getCatalogItem(ChannelAccount $account, string $marketplaceId, string $asin): array
    {
        $region = Marketplace::where('amazon_marketplace_id', $marketplaceId)->value('region') ?? $account->region;

        return $this->client->get(
            $account,
            '/catalog/2022-04-01/items/'.rawurlencode($asin),
            [
                'marketplaceIds' => $marketplaceId,
                'includedData' => ['attributes', 'classifications', 'dimensions', 'identifiers', 'images', 'productTypes', 'relationships', 'salesRanks', 'summaries'],
                'locale' => $this->localeForMarketplace($marketplaceId),
            ],
            $region
        );
    }

    public function getListingItem(ChannelAccount $account, string $marketplaceId, string $sku): array
    {
        $region = Marketplace::where('amazon_marketplace_id', $marketplaceId)->value('region') ?? $account->region;

        return $this->client->get(
            $account,
            '/listings/2021-08-01/items/'.rawurlencode((string) $account->account_identifier).'/'.rawurlencode($sku),
            [
                'marketplaceIds' => $marketplaceId,
                'includedData' => ['summaries', 'attributes', 'issues', 'offers', 'fulfillmentAvailability', 'relationships', 'productTypes'],
                'issueLocale' => $this->localeForMarketplace($marketplaceId),
            ],
            $region
        );
    }

    public function getProductTypeDefinition(ChannelAccount $account, string $marketplaceId, string $productType): array
    {
        $region = Marketplace::where('amazon_marketplace_id', $marketplaceId)->value('region') ?? $account->region;

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
            $region
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
        $region = Marketplace::where('amazon_marketplace_id', $marketplaceId)->value('region') ?? $account->region;

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
            $region
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
