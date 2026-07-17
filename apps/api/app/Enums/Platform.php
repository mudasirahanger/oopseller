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
}
