<?php

namespace App\Services\Channels;

use App\Enums\Platform;
use App\Models\ChannelAccount;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Services\Amazon\Contracts\SellerDataProvider;
use App\Services\Channels\Contracts\ChannelProvider;
use App\Services\Channels\Exceptions\UnsupportedChannelOperation;
use DateTimeInterface;

// Adapts the existing SP-API integration to the generic ChannelProvider
// contract. Amazon-specific callers may keep using SellerDataProvider directly;
// platform-agnostic code (sync orchestration, integration hub) goes through
// this adapter.
final class AmazonChannelProvider implements ChannelProvider
{
    public function __construct(private readonly SellerDataProvider $amazon) {}

    public function platform(): Platform
    {
        return Platform::Amazon;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.amazon.lwa_client_id'))
            && filled(config('services.amazon.lwa_client_secret'))
            && filled(config('services.amazon.application_id'))
            && filled(config('services.amazon.redirect_uri'));
    }

    public function authorizationUrl(string $state, array $options = []): ?string
    {
        $marketplace = $options['marketplace'] ?? null;
        abort_unless($marketplace instanceof Marketplace, 422, 'Amazon authorization requires a marketplace.');

        return $this->amazon->authorizationUrl(
            $state,
            $marketplace,
            (bool) ($options['draft'] ?? config('services.amazon.authorization_draft')),
        );
    }

    public function exchangeCode(string $code, array $options = []): array
    {
        return $this->amazon->exchangeAuthorizationCode($code);
    }

    public function verifyCredentials(ChannelAccount $account): void
    {
        // Amazon accounts connect through the dedicated OAuth/manual flows in
        // AmazonIntegrationController, which validate the refresh token via
        // the Sellers API themselves.
    }

    public function syncListings(ChannelAccount $account, array $options = []): iterable
    {
        $marketplaceId = (string) ($options['marketplace_id'] ?? '');
        abort_unless($marketplaceId !== '', 422, 'Amazon listing sync requires a marketplace_id.');

        return $this->amazon->importListings($account, $marketplaceId);
    }

    public function getOrders(ChannelAccount $account, DateTimeInterface $from, DateTimeInterface $to, array $options = []): iterable
    {
        $marketplaceIds = (array) ($options['marketplace_ids'] ?? []);

        if ($marketplaceIds === []) {
            $marketplaceIds = $account->marketplaces()
                ->wherePivot('enabled', true)
                ->pluck('amazon_marketplace_id')
                ->all();
        }

        abort_unless($marketplaceIds !== [], 422, 'No enabled Amazon marketplaces for this account.');

        foreach ($this->amazon->importOrders($account, $marketplaceIds, $from, $to) as $order) {
            $items = array_map(fn (array $item): array => [
                'external_id' => $item['ASIN'] ?? null,
                'sku' => $item['SellerSKU'] ?? null,
                'name' => $item['Title'] ?? null,
                'quantity' => (int) ($item['QuantityOrdered'] ?? 0),
                'unit_price' => $this->itemUnitPrice($item),
                'total' => (float) data_get($item, 'ItemPrice.Amount', 0),
            ], (array) ($order['OrderItems'] ?? []));

            $units = array_sum(array_column($items, 'quantity'));
            $subtotal = round((float) array_sum(array_column($items, 'total')), 2);
            $tax = round((float) array_sum(array_map(fn (array $item): float => (float) data_get($item, 'ItemTax.Amount', 0), (array) ($order['OrderItems'] ?? []))), 2);
            $shipping = round((float) array_sum(array_map(fn (array $item): float => (float) data_get($item, 'ShippingPrice.Amount', 0), (array) ($order['OrderItems'] ?? []))), 2);

            yield [
                'external_order_id' => (string) $order['AmazonOrderId'],
                'status' => $this->mapOrderStatus((string) ($order['OrderStatus'] ?? '')),
                'order_date' => (string) ($order['PurchaseDate'] ?? now()->toIso8601String()),
                'fulfillment_type' => match ($order['FulfillmentChannel'] ?? null) {
                    'AFN' => 'FBA',
                    'MFN' => 'FBM',
                    default => null,
                },
                'marketplace_id' => $order['MarketplaceId'] ?? null,
                'items' => $items,
                'units' => $units,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => (float) data_get($order, 'OrderTotal.Amount', $subtotal + $tax + $shipping),
                'currency' => (string) data_get($order, 'OrderTotal.CurrencyCode', 'INR'),
                'customer_city' => data_get($order, 'ShippingAddress.City'),
                'customer_state' => data_get($order, 'ShippingAddress.StateOrRegion'),
                'customer_pincode' => data_get($order, 'ShippingAddress.PostalCode'),
                'raw' => $order,
            ];
        }
    }

    /** @param array<string, mixed> $item */
    private function itemUnitPrice(array $item): ?float
    {
        $quantity = (int) ($item['QuantityOrdered'] ?? 0);
        $total = (float) data_get($item, 'ItemPrice.Amount', 0);

        return $quantity > 0 ? round($total / $quantity, 2) : null;
    }

    private function mapOrderStatus(string $status): string
    {
        return match ($status) {
            'Pending', 'PendingAvailability' => 'pending',
            'Unshipped', 'PartiallyShipped' => 'confirmed',
            'Shipped', 'InvoiceUnconfirmed' => 'shipped',
            'Canceled', 'Unfulfillable' => 'cancelled',
            default => 'pending',
        };
    }

    public function updateListing(ChannelAccount $account, Listing $listing, array $patches, array $options = []): array
    {
        abort_unless(filled($listing->seller_sku), 422, 'The listing has no seller SKU.');
        $productType = (string) ($options['product_type'] ?? '');
        abort_unless($productType !== '', 422, 'Amazon listing updates require a product_type.');

        return $this->amazon->publishListingPatch(
            $account,
            $listing->marketplace_id,
            $listing->seller_sku,
            $productType,
            $patches,
        );
    }

    public function getInventory(ChannelAccount $account, array $options = []): iterable
    {
        throw UnsupportedChannelOperation::for($this->platform(), 'inventory sync');
    }
}
