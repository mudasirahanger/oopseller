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

// Meesho Supplier API adapter. Auth is per-account API key + secret issued in
// the Meesho Supplier Panel (stored encrypted on the channel account); there
// is no server-level app registration, so the platform is always "configured".
final class MeeshoChannelProvider implements ChannelProvider
{
    public function platform(): Platform
    {
        return Platform::Meesho;
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
        throw UnsupportedChannelOperation::for($this->platform(), 'OAuth code exchange (Meesho uses API keys)');
    }

    public function verifyCredentials(ChannelAccount $account): void
    {
        // Meesho's supplier API is partner-gated with no stable public
        // validation endpoint; credential problems surface on the first sync.
    }

    public function syncListings(ChannelAccount $account, array $options = []): iterable
    {
        $page = 1;

        do {
            $response = $this->request($account)->get($this->baseUrl().'/api/v1/products', [
                'page' => $page,
                'page_size' => 50,
            ]);

            if ($response->failed()) {
                throw new ChannelApiException(
                    $this->platform(),
                    (string) ($response->json('message') ?: "Meesho API request failed with HTTP {$response->status()}."),
                    $response->status(),
                );
            }

            $payload = (array) $response->json();
            $items = (array) ($payload['products'] ?? $payload['data'] ?? []);

            foreach ($items as $item) {
                $price = $item['price'] ?? data_get($item, 'pricing.selling_price');

                yield [
                    'external_id' => (string) ($item['product_id'] ?? $item['id'] ?? ''),
                    'sku' => $item['sku'] ?? $item['supplier_sku'] ?? null,
                    'name' => (string) ($item['name'] ?? $item['title'] ?? 'Meesho product'),
                    'status' => strtolower((string) ($item['status'] ?? 'active')) === 'active' ? 'active' : 'inactive',
                    'image_url' => data_get($item, 'images.0') ?: ($item['image_url'] ?? null),
                    'price' => is_numeric($price) ? (float) $price : null,
                    'currency' => 'INR',
                    'marketplace_code' => 'meesho_in',
                    'raw' => $item,
                ];
            }

            $hasMore = count($items) === 50 && (bool) ($payload['has_more'] ?? true);
            $page++;
        } while ($hasMore && $page <= 200);
    }

    public function getOrders(ChannelAccount $account, DateTimeInterface $from, DateTimeInterface $to, array $options = []): iterable
    {
        $page = 1;

        do {
            $response = $this->request($account)->get($this->baseUrl().'/api/v1/orders', [
                'from_date' => $from->format('Y-m-d'),
                'to_date' => $to->format('Y-m-d'),
                'page' => $page,
                'page_size' => 50,
            ]);

            if ($response->failed()) {
                throw new ChannelApiException(
                    $this->platform(),
                    (string) ($response->json('message') ?: "Meesho API request failed with HTTP {$response->status()}."),
                    $response->status(),
                );
            }

            $payload = (array) $response->json();
            $orders = (array) ($payload['orders'] ?? $payload['data'] ?? []);

            foreach ($orders as $order) {
                $items = array_map(fn (array $item): array => [
                    'external_id' => $item['product_id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'] ?? null,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'unit_price' => is_numeric($item['price'] ?? null) ? (float) $item['price'] : null,
                    'total' => (float) ($item['total'] ?? (($item['price'] ?? 0) * ($item['quantity'] ?? 0))),
                ], (array) ($order['items'] ?? $order['order_items'] ?? []));
                $total = (float) ($order['total_amount'] ?? array_sum(array_column($items, 'total')));

                yield [
                    'external_order_id' => (string) ($order['order_id'] ?? $order['id'] ?? ''),
                    'status' => $this->mapOrderStatus((string) ($order['status'] ?? '')),
                    'order_date' => (string) ($order['created_at'] ?? $order['order_date'] ?? $from->format('c')),
                    'fulfillment_type' => 'platform_fulfilled',
                    'marketplace_id' => 'meesho_in',
                    'items' => $items,
                    'units' => array_sum(array_column($items, 'quantity')),
                    'subtotal' => round($total, 2),
                    'tax' => 0.0,
                    'shipping' => 0.0,
                    'total' => round($total, 2),
                    'currency' => 'INR',
                    'customer_city' => data_get($order, 'shipping_address.city'),
                    'customer_state' => data_get($order, 'shipping_address.state'),
                    'customer_pincode' => data_get($order, 'shipping_address.pincode'),
                    'raw' => $order,
                ];
            }

            $hasMore = count($orders) === 50 && (bool) ($payload['has_more'] ?? true);
            $page++;
        } while ($hasMore && $page <= 200);
    }

    private function mapOrderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'pending', 'created' => 'pending',
            'accepted', 'ready_to_ship', 'packed' => 'confirmed',
            'shipped', 'in_transit', 'out_for_delivery' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'return_requested', 'returned', 'rto' => 'returned',
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

        if (blank($credentials['api_key'] ?? null) || blank($credentials['api_secret'] ?? null)) {
            throw new ChannelApiException($this->platform(), 'This Meesho account has no API credentials. Reconnect it with a supplier API key and secret.');
        }

        return Http::withHeaders([
            'X-Api-Key' => (string) $credentials['api_key'],
            'X-Api-Secret' => (string) $credentials['api_secret'],
            'X-Supplier-Id' => (string) ($credentials['supplier_id'] ?? $account->account_identifier),
        ])
            ->acceptJson()
            ->timeout((int) config('services.meesho.timeout', 30))
            ->connectTimeout(10)
            ->retry(2, 500, throw: false);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.meesho.base_url'), '/');
    }
}
