<?php

namespace App\Services\Amazon;

use App\Models\ChannelAccount;
use App\Services\Amazon\Contracts\SellerDataProvider;
use Illuminate\Support\Facades\Cache;

final class AmazonProductTypeDefinitionService
{
    public function __construct(private readonly SellerDataProvider $provider) {}

    public function definition(ChannelAccount $account, string $marketplaceId, string $productType): array
    {
        $cacheKey = sprintf(
            'amazon_ptd:%d:%s:%s',
            $account->id,
            $marketplaceId,
            strtoupper($productType),
        );

        return Cache::remember($cacheKey, now()->addHours(6), fn (): array => $this->provider->getProductTypeDefinition($account, $marketplaceId, $productType)
        );
    }

    /**
     * Property groups are presentation metadata, not the final validator. We
     * expose possible mismatches as warnings and rely on Amazon's
     * VALIDATION_PREVIEW response as the source of truth.
     */
    public function unsupportedPatchAttributes(array $definition, array $patches): array
    {
        $supported = collect($definition['propertyGroups'] ?? [])
            ->flatMap(fn (array $group) => $group['propertyNames'] ?? [])
            ->filter()
            ->unique()
            ->values();

        if ($supported->isEmpty()) {
            return [];
        }

        return collect($patches)
            ->map(fn (array $patch) => str($patch['path'] ?? '')->after('/attributes/')->before('/')->toString())
            ->filter()
            ->unique()
            ->reject(fn (string $attribute) => $supported->contains($attribute))
            ->values()
            ->all();
    }
}
