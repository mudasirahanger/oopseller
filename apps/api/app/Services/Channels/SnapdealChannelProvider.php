<?php

namespace App\Services\Channels;

use App\Enums\Platform;
use App\Models\ChannelAccount;
use App\Models\Listing;
use App\Services\Channels\Contracts\ChannelProvider;
use App\Services\Channels\Exceptions\ChannelApiException;
use App\Services\Channels\Exceptions\UnsupportedChannelOperation;
use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// Snapdeal Seller API adapter. Auth is a per-account seller code + auth token
// issued through Snapdeal Seller Zone (stored encrypted on the channel
// account). Listings are SUPC based.
final class SnapdealChannelProvider implements ChannelProvider
{
    public function platform(): Platform
    {
        return Platform::Snapdeal;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state, array $options = []): ?string
    {
        return null;
    }

    public function exchangeCode(string $code): array
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'OAuth code exchange (Snapdeal uses auth tokens)');
    }

    public function syncListings(ChannelAccount $account, array $options = []): iterable
    {
        $offset = 0;
        $limit = 50;

        do {
            $response = $this->request($account)->get($this->baseUrl().'/seller/listings', [
                'offset' => $offset,
                'limit' => $limit,
            ]);

            if ($response->failed()) {
                throw new ChannelApiException(
                    $this->platform(),
                    (string) ($response->json('message') ?: "Snapdeal API request failed with HTTP {$response->status()}."),
                    $response->status(),
                );
            }

            $payload = (array) $response->json();
            $items = (array) ($payload['listings'] ?? $payload['data'] ?? []);

            foreach ($items as $item) {
                $price = $item['sellingPrice'] ?? $item['mrp'] ?? null;

                yield [
                    'external_id' => (string) ($item['supc'] ?? $item['id'] ?? ''),
                    'sku' => $item['sku'] ?? $item['sellerSku'] ?? null,
                    'name' => (string) ($item['productName'] ?? $item['title'] ?? 'Snapdeal listing'),
                    'status' => strtolower((string) ($item['status'] ?? 'live')) === 'live' ? 'active' : 'inactive',
                    'image_url' => data_get($item, 'images.0') ?: null,
                    'price' => is_numeric($price) ? (float) $price : null,
                    'currency' => 'INR',
                    'marketplace_code' => 'snapdeal_in',
                    'raw' => $item,
                ];
            }

            $offset += $limit;
            $hasMore = count($items) === $limit;
        } while ($hasMore && $offset <= 10000);
    }

    public function getOrders(ChannelAccount $account, DateTimeInterface $from, DateTimeInterface $to, array $options = []): iterable
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'order sync (planned for the orders phase)');
    }

    public function updateListing(ChannelAccount $account, Listing $listing, array $patches, array $options = []): array
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'listing updates');
    }

    public function getInventory(ChannelAccount $account, array $options = []): iterable
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'inventory sync');
    }

    private function request(ChannelAccount $account): PendingRequest
    {
        $credentials = (array) $account->credentials;

        if (blank($credentials['auth_token'] ?? null)) {
            throw new ChannelApiException($this->platform(), 'This Snapdeal account has no auth token. Reconnect it with Seller Zone API credentials.');
        }

        return Http::withHeaders([
            'Authorization' => 'Bearer '.$credentials['auth_token'],
            'X-Seller-Code' => (string) ($credentials['seller_code'] ?? $account->account_identifier),
        ])
            ->acceptJson()
            ->timeout((int) config('services.snapdeal.timeout', 30))
            ->connectTimeout(10)
            ->retry(2, 500, throw: false);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.snapdeal.base_url'), '/');
    }
}
