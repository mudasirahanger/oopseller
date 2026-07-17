<?php

namespace App\Services\Amazon;

use Illuminate\Support\Arr;

final class AmazonDataMapper
{
    public function listing(array $item, string $marketplaceId): array
    {
        $summary = collect($item['summaries'] ?? [])->firstWhere('marketplaceId', $marketplaceId)
            ?? collect($item['summaries'] ?? [])->first()
            ?? [];
        $attributes = $item['attributes'] ?? [];
        $matchedProductType = collect($item['productTypes'] ?? [])->firstWhere('marketplaceId', $marketplaceId);
        $productType = data_get($matchedProductType, 'productType')
            ?? data_get($item, 'productTypes.0.productType')
            ?? data_get($summary, 'productType');
        $asin = data_get($summary, 'asin') ?? data_get($item, 'identifiers.0.asin');

        $title = data_get($summary, 'itemName') ?: $this->firstAttributeValue($attributes, 'item_name');
        $bullets = $this->attributeValues($attributes, 'bullet_point');
        $description = $this->firstAttributeValue($attributes, 'product_description');
        $backendTerms = $this->attributeValues($attributes, 'generic_keyword');
        $mainImage = data_get($summary, 'mainImage.link');

        return [
            'asin' => $asin,
            'sku' => $item['sku'] ?? null,
            'name' => $title ?: $asin ?: ($item['sku'] ?? 'Amazon listing'),
            'product_type' => $productType,
            'image_url' => $mainImage,
            'product_metadata' => [
                'summary' => $summary,
                'status' => $item['status'] ?? [],
                'created_date' => $item['createdDate'] ?? null,
                'last_updated_date' => $item['lastUpdatedDate'] ?? null,
            ],
            'listing' => [
                'seller_sku' => $item['sku'] ?? null,
                'title' => $title,
                'bullet_points' => $bullets,
                'description' => $description,
                'backend_terms' => $backendTerms,
                'attributes' => $attributes,
                'amazon_issues' => $item['issues'] ?? [],
                'offers' => $item['offers'] ?? [],
                'fulfillment_availability' => $item['fulfillmentAvailability'] ?? [],
                'relationships' => $item['relationships'] ?? [],
                'product_types' => $item['productTypes'] ?? [],
                'raw_payload' => $item,
                'image_count' => $mainImage ? 1 : 0,
                'status' => $this->listingStatus($item['status'] ?? []),
            ],
        ];
    }

    public function catalog(array $item, string $marketplaceId): array
    {
        $summary = collect($item['summaries'] ?? [])->firstWhere('marketplaceId', $marketplaceId)
            ?? collect($item['summaries'] ?? [])->first()
            ?? [];
        $matchedImages = collect($item['images'] ?? [])->firstWhere('marketplaceId', $marketplaceId);
        $images = data_get($matchedImages, 'images') ?? data_get($item, 'images.0.images', []);
        $mainImage = collect($images)->firstWhere('variant', 'MAIN')['link']
            ?? data_get($images, '0.link');
        $matchedProductType = collect($item['productTypes'] ?? [])->firstWhere('marketplaceId', $marketplaceId);
        $productType = data_get($matchedProductType, 'productType') ?? data_get($item, 'productTypes.0.productType');

        return [
            'asin' => $item['asin'] ?? null,
            'name' => data_get($summary, 'itemName') ?: ($item['asin'] ?? 'Amazon catalog item'),
            'product_type' => $productType,
            'image_url' => $mainImage,
            'metadata' => [
                'attributes' => $item['attributes'] ?? [],
                'classifications' => $item['classifications'] ?? [],
                'dimensions' => $item['dimensions'] ?? [],
                'identifiers' => $item['identifiers'] ?? [],
                'images' => $item['images'] ?? [],
                'relationships' => $item['relationships'] ?? [],
                'sales_ranks' => $item['salesRanks'] ?? [],
                'summaries' => $item['summaries'] ?? [],
            ],
        ];
    }

    private function firstAttributeValue(array $attributes, string $key): ?string
    {
        return Arr::first($this->attributeValues($attributes, $key));
    }

    private function attributeValues(array $attributes, string $key): array
    {
        return collect($attributes[$key] ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['value'] ?? null) : null)
            ->filter(fn ($value) => is_scalar($value) && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    private function listingStatus(array|string $status): string
    {
        $statuses = is_array($status) ? $status : [$status];

        if (in_array('BUYABLE', $statuses, true)) {
            return 'active';
        }

        if (in_array('DISCOVERABLE', $statuses, true)) {
            return 'discoverable';
        }

        return $statuses === [] ? 'unknown' : strtolower((string) $statuses[0]);
    }
}
