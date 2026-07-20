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

    public function exchangeCode(string $code, array $options = []): array
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'OAuth code exchange (Snapdeal uses auth tokens)');
    }

    public function verifyCredentials(ChannelAccount $account): void
    {
        // Snapdeal's seller API is program-gated with no stable public
        // validation endpoint; credential problems surface on the first sync.
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
        $offset = 0;
        $limit = 50;

        do {
            $response = $this->request($account)->get($this->baseUrl().'/seller/orders', [
                'fromDate' => $from->format('Y-m-d'),
                'toDate' => $to->format('Y-m-d'),
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
            $orders = (array) ($payload['orders'] ?? $payload['data'] ?? []);

            foreach ($orders as $order) {
                $items = array_map(fn (array $item): array => [
                    'external_id' => $item['supc'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['productName'] ?? null,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'unit_price' => is_numeric($item['sellingPrice'] ?? null) ? (float) $item['sellingPrice'] : null,
                    'total' => (float) ($item['totalPrice'] ?? (($item['sellingPrice'] ?? 0) * ($item['quantity'] ?? 0))),
                ], (array) ($order['items'] ?? $order['orderItems'] ?? []));
                $total = (float) ($order['orderAmount'] ?? array_sum(array_column($items, 'total')));

                yield [
                    'external_order_id' => (string) ($order['orderCode'] ?? $order['id'] ?? ''),
                    'status' => $this->mapOrderStatus((string) ($order['status'] ?? '')),
                    'order_date' => (string) ($order['orderDate'] ?? $from->format('c')),
                    'fulfillment_type' => 'platform_fulfilled',
                    'marketplace_id' => 'snapdeal_in',
                    'items' => $items,
                    'units' => array_sum(array_column($items, 'quantity')),
                    'subtotal' => round($total, 2),
                    'tax' => 0.0,
                    'shipping' => 0.0,
                    'total' => round($total, 2),
                    'currency' => 'INR',
                    'customer_city' => data_get($order, 'shippingAddress.city'),
                    'customer_state' => data_get($order, 'shippingAddress.state'),
                    'customer_pincode' => data_get($order, 'shippingAddress.pincode'),
                    'raw' => $order,
                ];
            }

            $offset += $limit;
            $hasMore = count($orders) === $limit;
        } while ($hasMore && $offset <= 20000);
    }

    private function mapOrderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'new', 'pending' => 'pending',
            'confirmed', 'packed', 'ready_to_ship' => 'confirmed',
            'shipped', 'in_transit' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'returned', 'rto' => 'returned',
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
