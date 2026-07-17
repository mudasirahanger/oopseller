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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// Flipkart Marketplace Seller API adapter. Auth is OAuth 2.0 against the
// Flipkart API gateway; listings are Listings API v3 (FSN/SKU based).
final class FlipkartChannelProvider implements ChannelProvider
{
    public function platform(): Platform
    {
        return Platform::Flipkart;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.flipkart.client_id'))
            && filled(config('services.flipkart.client_secret'))
            && filled(config('services.flipkart.redirect_uri'));
    }

    public function authorizationUrl(string $state, array $options = []): ?string
    {
        abort_unless($this->isConfigured(), 422, 'Flipkart API credentials are not configured on the server.');

        return $this->baseUrl().'/oauth-service/oauth/authorize?'.http_build_query([
            'client_id' => config('services.flipkart.client_id'),
            'redirect_uri' => config('services.flipkart.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'Seller_Api',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->withBasicAuth((string) config('services.flipkart.client_id'), (string) config('services.flipkart.client_secret'))
            ->acceptJson()
            ->timeout((int) config('services.flipkart.timeout', 30))
            ->retry(2, 400, throw: false)
            ->get($this->baseUrl().'/oauth-service/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('services.flipkart.redirect_uri'),
                'state' => 'token-exchange',
            ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new ChannelApiException(
                $this->platform(),
                (string) ($response->json('error_description') ?: 'Flipkart token exchange failed.'),
                $response->status(),
            );
        }

        return (array) $response->json();
    }

    public function syncListings(ChannelAccount $account, array $options = []): iterable
    {
        // Listings API v3: paginated search over all active/inactive listings.
        $body = ['filter' => ['listingState' => $options['listing_state'] ?? null]];
        $url = $this->baseUrl().'/sellers/listings/v3/search';

        do {
            $response = $this->request($account)->post($url, array_filter($body));

            if ($response->failed()) {
                $this->throwForResponse($response->status(), (array) ($response->json() ?? []));
            }

            $payload = (array) $response->json();

            foreach ((array) ($payload['listings'] ?? []) as $item) {
                $price = data_get($item, 'price.mrp') ?? data_get($item, 'price.sellingPrice');

                yield [
                    'external_id' => (string) ($item['fsn'] ?? $item['productId'] ?? ''),
                    'sku' => $item['skuId'] ?? null,
                    'name' => (string) ($item['title'] ?? $item['productTitle'] ?? 'Flipkart listing'),
                    'status' => strtoupper((string) ($item['listingState'] ?? '')) === 'ACTIVE' ? 'active' : 'inactive',
                    'image_url' => data_get($item, 'imageUrls.0') ?: null,
                    'price' => is_numeric($price) ? (float) $price : null,
                    'currency' => 'INR',
                    'marketplace_code' => 'flipkart_in',
                    'raw' => $item,
                ];
            }

            $nextToken = data_get($payload, 'nextPageUrl') ?: data_get($payload, 'nextToken');
            $url = $nextToken ? $this->baseUrl().$nextToken : null;
            $body = [];
        } while ($url);
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
        return Http::withToken($this->accessToken($account))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.flipkart.timeout', 30))
            ->connectTimeout(10)
            ->retry(2, 500, throw: false);
    }

    private function accessToken(ChannelAccount $account): string
    {
        if (blank($account->refresh_token)) {
            throw new ChannelApiException($this->platform(), 'This Flipkart account has no refresh token. Reconnect the seller account.');
        }

        return Cache::remember('flipkart_access_token:'.$account->getKey(), now()->addMinutes(50), function () use ($account): string {
            $response = Http::asForm()
                ->withBasicAuth((string) config('services.flipkart.client_id'), (string) config('services.flipkart.client_secret'))
                ->acceptJson()
                ->timeout((int) config('services.flipkart.timeout', 30))
                ->get($this->baseUrl().'/oauth-service/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ]);

            if ($response->failed() || blank($response->json('access_token'))) {
                $this->throwForResponse($response->status(), (array) ($response->json() ?? []));
            }

            $account->forceFill(['token_last_refreshed_at' => now()])->saveQuietly();

            return (string) $response->json('access_token');
        });
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.flipkart.base_url'), '/');
    }

    private function throwForResponse(int $status, array $payload): never
    {
        throw new ChannelApiException(
            $this->platform(),
            (string) (data_get($payload, 'error_description') ?: data_get($payload, 'message') ?: "Flipkart API request failed with HTTP {$status}."),
            $status,
        );
    }
}
