<?php

namespace App\Enums;

enum Platform: string
{
    case Amazon = 'amazon';
    case Flipkart = 'flipkart';
    case Meesho = 'meesho';
    case Snapdeal = 'snapdeal';
    case Myntra = 'myntra';
    case Ajio = 'ajio';
    case Shopify = 'shopify';
    case WooCommerce = 'woocommerce';
    case OpenCart = 'opencart';
    case Magento = 'magento';
    case Walmart = 'walmart';

    public function label(): string
    {
        return match ($this) {
            self::Amazon => 'Amazon',
            self::Flipkart => 'Flipkart',
            self::Meesho => 'Meesho',
            self::Snapdeal => 'Snapdeal',
            self::Myntra => 'Myntra',
            self::Ajio => 'AJIO',
            self::Shopify => 'Shopify',
            self::WooCommerce => 'WooCommerce',
            self::OpenCart => 'OpenCart',
            self::Magento => 'Magento 2',
            self::Walmart => 'Walmart Marketplace',
        };
    }

    public function authType(): string
    {
        return match ($this) {
            self::Amazon, self::Flipkart, self::Shopify, self::Walmart => 'oauth',
            self::Magento => 'token',
            default => 'api_key',
        };
    }

    public function docsUrl(): string
    {
        return match ($this) {
            self::Amazon => 'https://developer-docs.amazon.com/sp-api',
            self::Flipkart => 'https://seller.flipkart.com/api-docs/FMSAPI.html',
            self::Meesho => 'https://supplier.meesho.com',
            self::Snapdeal => 'https://sellers.snapdeal.com',
            self::Myntra => 'https://partners.myntrainfo.com',
            self::Ajio => 'https://seller.ajio.com',
            self::Shopify => 'https://shopify.dev/docs/api/admin-rest',
            self::WooCommerce => 'https://woocommerce.github.io/woocommerce-rest-api-docs',
            self::OpenCart => 'https://docs.opencart.com',
            self::Magento => 'https://developer.adobe.com/commerce/webapi/rest',
            self::Walmart => 'https://developer.walmart.com',
        };
    }

    /** @return array<int, string> */
    public function setupSteps(): array
    {
        return match ($this) {
            self::Amazon => [
                'Register a Selling Partner API application in the Amazon Developer Console.',
                'Set AMAZON_LWA_CLIENT_ID, AMAZON_LWA_CLIENT_SECRET, AMAZON_SPAPI_APPLICATION_ID, and AMAZON_REDIRECT_URI in the server environment. The redirect URI must exactly match the one registered with Amazon.',
                'Click Connect, pick the client and Seller Central marketplace, and approve the consent screen with the seller account.',
                'The initial listing sync is queued automatically after authorization.',
            ],
            self::Flipkart => [
                'Register an application in the Flipkart Seller API portal (seller.flipkart.com → API access) to get an application ID and secret.',
                'Set FLIPKART_CLIENT_ID, FLIPKART_CLIENT_SECRET, and FLIPKART_REDIRECT_URI in the server environment. The redirect URI must match the one whitelisted with Flipkart.',
                'Click Connect, pick the client, and sign in with the Flipkart seller account on the consent screen.',
                'Run a listing sync from the connected account card to import Flipkart listings (FSNs).',
            ],
            self::Meesho => [
                'Log in to the Meesho Supplier Panel and open the API/integration settings (partner approval may be required by Meesho).',
                'Generate the supplier API credentials (supplier ID, API key, API secret).',
                'Click Connect, pick the client, and paste the credentials — they are stored encrypted.',
                'Run a listing sync from the connected account card to import the catalog.',
            ],
            self::Snapdeal => [
                'Log in to the Snapdeal Seller Zone and request API access to get your seller code and auth token.',
                'Click Connect, pick the client, and paste the seller code and auth token — they are stored encrypted.',
                'Run a listing sync from the connected account card to import Snapdeal listings (SUPCs).',
            ],
            default => [
                'This platform adapter is on the roadmap and cannot be connected yet.',
                'The channel core (accounts, credentials, sync runs) is already platform-ready, so the adapter plugs in without schema changes.',
            ],
        };
    }

    /**
     * Credential fields collected by the connect form for API-key platforms.
     *
     * @return array<int, array{key: string, label: string, secret: bool}>
     */
    public function credentialFields(): array
    {
        return match ($this) {
            self::Meesho => [
                ['key' => 'supplier_id', 'label' => 'Supplier ID', 'secret' => false],
                ['key' => 'api_key', 'label' => 'API key', 'secret' => true],
                ['key' => 'api_secret', 'label' => 'API secret', 'secret' => true],
            ],
            self::Snapdeal => [
                ['key' => 'seller_code', 'label' => 'Seller code', 'secret' => false],
                ['key' => 'auth_token', 'label' => 'Auth token', 'secret' => true],
            ],
            default => [],
        };
    }
}
