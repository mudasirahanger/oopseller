<?php

namespace App\Services\Channels;

use App\Enums\Platform;
use App\Models\ChannelAccount;
use App\Models\Listing;
use App\Services\Channels\Contracts\ChannelProvider;
use App\Services\Channels\Exceptions\ChannelApiException;
use App\Services\Channels\Exceptions\UnsupportedChannelOperation;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// Flipkart Marketplace Seller API adapter (per the official FMS API docs at
// seller.flipkart.com/api-docs). Flipkart has two application types:
// - Third-party (Partner Dashboard): authorization-code OAuth with seller
//   consent (authorizationUrl/exchangeCode below, server-level app creds).
// - Self-access (Seller Dashboard > Developer Access): the seller's own
//   app_id/app_secret with grant_type=client_credentials — no consent screen.
//   Using self-access credentials on the consent URL is rejected by Flipkart
//   with its generic "Oops! Something went wrong" page.
// Listings are Listings API v3 (FSN/SKU based).
final class FlipkartChannelProvider implements ChannelProvider
{
    public function platform(): Platform
    {
        return Platform::Flipkart;
    }

    public function isConfigured(): bool
    {
        // Self-access (per-account app credentials) needs no server-level
        // configuration; the OAuth partner flow separately requires the
        // FLIPKART_* env vars and reports its own error when missing.
        return true;
    }

    public function authorizationUrl(string $state, array $options = []): ?string
    {
        abort_unless(
            filled(config('services.flipkart.client_id'))
                && filled(config('services.flipkart.client_secret'))
                && filled(config('services.flipkart.redirect_uri')),
            422,
            'Flipkart partner-app credentials are not configured on the server. Connect with self-access app credentials instead, or set FLIPKART_CLIENT_ID/SECRET/REDIRECT_URI.',
        );

        return $this->baseUrl().'/oauth-service/oauth/authorize?'.http_build_query([
            'client_id' => config('services.flipkart.client_id'),
            'redirect_uri' => config('services.flipkart.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'Seller_Api',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, array $options = []): array
    {
        $data = $this->tokenRequest(
            (string) config('services.flipkart.client_id'),
            (string) config('services.flipkart.client_secret'),
            array_filter([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('services.flipkart.redirect_uri'),
                // The docs include the original state in the token request.
                'state' => $options['state'] ?? null,
            ]),
        );

        return $data;
    }

    public function verifyCredentials(ChannelAccount $account): void
    {
        Cache::forget('flipkart_access_token:'.$account->getKey());
        // Throws ChannelApiException when Flipkart rejects the credentials.
        $this->accessToken($account);
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
        // Shipments API v3: paginated search over order items in the window.
        $body = [
            'filter' => [
                'orderDate' => [
                    'fromDate' => $from->format('Y-m-d\TH:i:s\Z'),
                    'toDate' => $to->format('Y-m-d\TH:i:s\Z'),
                ],
            ],
            'pagination' => ['pageSize' => 20],
        ];
        $url = $this->baseUrl().'/sellers/v3/shipments/filter';

        do {
            $response = $this->request($account)->post($url, $body);

            if ($response->failed()) {
                $this->throwForResponse($response->status(), (array) ($response->json() ?? []));
            }

            $payload = (array) $response->json();

            foreach ((array) ($payload['shipments'] ?? []) as $shipment) {
                $orderItems = (array) ($shipment['orderItems'] ?? []);
                $items = array_map(fn (array $item): array => [
                    'external_id' => $item['fsn'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['title'] ?? null,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'unit_price' => is_numeric(data_get($item, 'priceComponents.sellingPrice')) ? (float) data_get($item, 'priceComponents.sellingPrice') : null,
                    'total' => (float) data_get($item, 'priceComponents.totalPrice', 0),
                ], $orderItems);

                $total = round((float) array_sum(array_column($items, 'total')), 2);

                yield [
                    'external_order_id' => (string) ($shipment['orderId'] ?? data_get($orderItems, '0.orderId') ?? $shipment['shipmentId'] ?? ''),
                    'status' => $this->mapOrderStatus((string) ($shipment['status'] ?? data_get($orderItems, '0.status') ?? '')),
                    'order_date' => (string) (data_get($orderItems, '0.orderDate') ?: $from->format('c')),
                    'fulfillment_type' => 'platform_fulfilled',
                    'marketplace_id' => 'flipkart_in',
                    'items' => $items,
                    'units' => array_sum(array_column($items, 'quantity')),
                    'subtotal' => $total,
                    'tax' => 0.0,
                    'shipping' => 0.0,
                    'total' => $total,
                    'currency' => 'INR',
                    'customer_city' => data_get($shipment, 'deliveryAddress.city'),
                    'customer_state' => data_get($shipment, 'deliveryAddress.state'),
                    'customer_pincode' => data_get($shipment, 'deliveryAddress.pincode'),
                    'raw' => $shipment,
                ];
            }

            $nextUrl = data_get($payload, 'nextPageUrl');
            $url = $nextUrl ? $this->baseUrl().$nextUrl : null;
            $body = [];
        } while ($url);
    }

    private function mapOrderStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'APPROVED', 'PACKING_IN_PROGRESS', 'PACKED', 'READY_TO_DISPATCH', 'FORM_FAILED' => 'confirmed',
            'SHIPPED', 'PICKUP_COMPLETE' => 'shipped',
            'DELIVERED' => 'delivered',
            'CANCELLED' => 'cancelled',
            'RETURNED', 'RETURN_REQUESTED' => 'returned',
            default => 'pending',
        };
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
        $credentials = (array) $account->credentials;
        $selfAccess = filled($credentials['app_id'] ?? null) && filled($credentials['app_secret'] ?? null);

        if (! $selfAccess && blank($account->refresh_token)) {
            throw new ChannelApiException($this->platform(), 'This Flipkart account has no credentials. Reconnect it with self-access app credentials or re-authorize.');
        }

        return Cache::remember('flipkart_access_token:'.$account->getKey(), now()->addMinutes(50), function () use ($account, $credentials, $selfAccess): string {
            // Self-access apps (Seller Dashboard > Developer Access) use
            // grant_type=client_credentials with the seller's own app
            // credentials; partner apps refresh with the server-level ones.
            $data = $selfAccess
                ? $this->tokenRequest(
                    (string) $credentials['app_id'],
                    (string) $credentials['app_secret'],
                    ['grant_type' => 'client_credentials', 'scope' => 'Seller_Api'],
                )
                : $this->tokenRequest(
                    (string) config('services.flipkart.client_id'),
                    (string) config('services.flipkart.client_secret'),
                    ['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token],
                );

            $account->forceFill(['token_last_refreshed_at' => now()])->saveQuietly();

            return (string) $data['access_token'];
        });
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function tokenRequest(string $clientId, string $clientSecret, array $query): array
    {
        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->acceptJson()
                ->timeout((int) config('services.flipkart.timeout', 30))
                ->connectTimeout(10)
                ->retry(2, 400, throw: false)
                ->get($this->baseUrl().'/oauth-service/oauth/token', $query);
        } catch (ConnectionException $exception) {
            throw new ChannelApiException(
                $this->platform(),
                "Could not reach Flipkart's authorization servers: {$exception->getMessage()}",
            );
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            $this->throwForResponse($response->status(), (array) ($response->json() ?? []));
        }

        return (array) $response->json();
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
