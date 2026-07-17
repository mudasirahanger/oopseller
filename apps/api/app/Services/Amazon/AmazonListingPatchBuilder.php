<?php

namespace App\Services\Amazon;

final class AmazonListingPatchBuilder
{
    public function build(array $content, string $marketplaceId): array
    {
        $locale = $this->localeForMarketplace($marketplaceId);
        $patches = [];

        if (array_key_exists('title', $content)) {
            $patches[] = $this->replace('/attributes/item_name', [[
                'value' => $content['title'],
                'language_tag' => $locale,
                'marketplace_id' => $marketplaceId,
            ]]);
        }

        if (array_key_exists('bullet_points', $content)) {
            $patches[] = $this->replace('/attributes/bullet_point', collect($content['bullet_points'])
                ->filter()
                ->map(fn (string $value) => [
                    'value' => $value,
                    'language_tag' => $locale,
                    'marketplace_id' => $marketplaceId,
                ])->values()->all());
        }

        if (array_key_exists('description', $content)) {
            $patches[] = $this->replace('/attributes/product_description', [[
                'value' => $content['description'],
                'language_tag' => $locale,
                'marketplace_id' => $marketplaceId,
            ]]);
        }

        if (array_key_exists('backend_terms', $content)) {
            $patches[] = $this->replace('/attributes/generic_keyword', collect($content['backend_terms'])
                ->filter()
                ->map(fn (string $value) => [
                    'value' => $value,
                    'language_tag' => $locale,
                    'marketplace_id' => $marketplaceId,
                ])->values()->all());
        }

        return $patches;
    }

    private function replace(string $path, array $value): array
    {
        return ['op' => 'replace', 'path' => $path, 'value' => $value];
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
