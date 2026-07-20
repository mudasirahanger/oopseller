<?php

namespace App\Services\Channels\Contracts;

use App\Enums\Platform;
use App\Models\ChannelAccount;
use App\Models\Listing;

interface ChannelProvider
{
    public function platform(): Platform;

    /**
     * Whether the platform credentials for this installation are configured.
     */
    public function isConfigured(): bool;

    /**
     * OAuth consent URL, or null for API-key platforms (connect via storeCredentials).
     *
     * @param  array<string, mixed>  $options  platform-specific options (marketplace, draft, ...)
     */
    public function authorizationUrl(string $state, array $options = []): ?string;

    /**
     * Exchange an OAuth authorization code for tokens. API-key platforms throw
     * UnsupportedChannelOperation.
     *
     * @param  array<string, mixed>  $options  flow context (state, redirect_uri, ...)
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, array $options = []): array;

    /**
     * Validate the per-account credentials stored on the channel account
     * (API keys / self-access app credentials) against the platform, throwing
     * ChannelApiException when the platform rejects them. Platforms that
     * cannot cheaply validate (partner-gated APIs) may no-op; failures then
     * surface on the first sync instead.
     */
    public function verifyCredentials(ChannelAccount $account): void;

    /**
     * Stream the account's listings from the platform.
     *
     * @param  array<string, mixed>  $options
     * @return iterable<array<string, mixed>>
     */
    public function syncListings(ChannelAccount $account, array $options = []): iterable;

    /**
     * Stream orders in the given window. Platforms without order support throw
     * UnsupportedChannelOperation.
     *
     * @param  array<string, mixed>  $options
     * @return iterable<array<string, mixed>>
     */
    public function getOrders(ChannelAccount $account, \DateTimeInterface $from, \DateTimeInterface $to, array $options = []): iterable;

    /**
     * Push listing content changes to the platform.
     *
     * @param  array<int, array<string, mixed>>  $patches
     * @return array<string, mixed>
     */
    public function updateListing(ChannelAccount $account, Listing $listing, array $patches, array $options = []): array;

    /**
     * Stream inventory levels. Platforms without inventory support throw
     * UnsupportedChannelOperation.
     *
     * @param  array<string, mixed>  $options
     * @return iterable<array<string, mixed>>
     */
    public function getInventory(ChannelAccount $account, array $options = []): iterable;
}
