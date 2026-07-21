<?php

namespace App\Services\Amazon;

use App\Models\Marketplace;
use RuntimeException;

final class AmazonConfiguration
{
    private const API_ENDPOINTS = [
        'na' => 'https://sellingpartnerapi-na.amazon.com',
        'eu' => 'https://sellingpartnerapi-eu.amazon.com',
        'fe' => 'https://sellingpartnerapi-fe.amazon.com',
    ];

    // Amazon SP-API region per marketplace country. Stable/documented
    // (https://developer-docs.amazon.com/sp-api/docs/marketplace-ids) —
    // NOT the same thing as the region of the seller account that happens
    // to be connecting; a seller can participate in marketplaces across
    // multiple of these at once.
    private const COUNTRY_REGIONS = [
        'CA' => 'na', 'US' => 'na', 'MX' => 'na', 'BR' => 'na',
        'IE' => 'eu', 'ES' => 'eu', 'GB' => 'eu', 'FR' => 'eu', 'BE' => 'eu',
        'NL' => 'eu', 'DE' => 'eu', 'IT' => 'eu', 'SE' => 'eu', 'ZA' => 'eu',
        'PL' => 'eu', 'EG' => 'eu', 'TR' => 'eu', 'SA' => 'eu', 'AE' => 'eu',
        'IN' => 'eu',
        'SG' => 'fe', 'AU' => 'fe', 'JP' => 'fe',
    ];

    private const SELLER_CENTRAL_URLS = [
        'CA' => 'https://sellercentral.amazon.ca',
        'US' => 'https://sellercentral.amazon.com',
        'MX' => 'https://sellercentral.amazon.com.mx',
        'BR' => 'https://sellercentral.amazon.com.br',
        'IE' => 'https://sellercentral.amazon.ie',
        'ES' => 'https://sellercentral-europe.amazon.com',
        'GB' => 'https://sellercentral-europe.amazon.com',
        'FR' => 'https://sellercentral-europe.amazon.com',
        'BE' => 'https://sellercentral.amazon.com.be',
        'NL' => 'https://sellercentral.amazon.nl',
        'DE' => 'https://sellercentral-europe.amazon.com',
        'IT' => 'https://sellercentral-europe.amazon.com',
        'SE' => 'https://sellercentral.amazon.se',
        'ZA' => 'https://sellercentral.amazon.co.za',
        'PL' => 'https://sellercentral.amazon.pl',
        'EG' => 'https://sellercentral.amazon.eg',
        'TR' => 'https://sellercentral.amazon.com.tr',
        'SA' => 'https://sellercentral.amazon.sa',
        'AE' => 'https://sellercentral.amazon.ae',
        'IN' => 'https://sellercentral.amazon.in',
        'SG' => 'https://sellercentral.amazon.sg',
        'AU' => 'https://sellercentral.amazon.com.au',
        'JP' => 'https://sellercentral.amazon.co.jp',
    ];

    public function assertConfigured(): void
    {
        foreach (['lwa_client_id', 'lwa_client_secret', 'application_id', 'redirect_uri'] as $key) {
            if (blank(config("services.amazon.{$key}"))) {
                throw new RuntimeException("Amazon SP-API configuration is incomplete: services.amazon.{$key} is missing.");
            }
        }
    }

    /**
     * $sandbox is per-account (ChannelAccount.metadata['sandbox']), falling
     * back to the AMAZON_SPAPI_SANDBOX server default at the call site —
     * this lets a sandbox test account and a real account coexist rather
     * than sandbox being an all-or-nothing server setting.
     */
    public function endpoint(string $region, bool $sandbox = false): string
    {
        $endpoint = self::API_ENDPOINTS[strtolower($region)] ?? null;

        if (! $endpoint) {
            throw new RuntimeException("Unsupported Amazon SP-API region [{$region}].");
        }

        if ($sandbox) {
            return preg_replace('#^https://#', 'https://sandbox.', $endpoint) ?: $endpoint;
        }

        return $endpoint;
    }

    /**
     * The correct SP-API region for a marketplace's own country — falls back
     * to $accountRegion only when the country code is unrecognized, rather
     * than assuming every marketplace a seller participates in shares the
     * region of whichever marketplace they originally connected through.
     */
    public function regionForCountryCode(string $countryCode, string $accountRegion): string
    {
        return self::COUNTRY_REGIONS[strtoupper($countryCode)] ?? $accountRegion;
    }

    public function sellerCentralUrl(Marketplace $marketplace): string
    {
        return self::SELLER_CENTRAL_URLS[$marketplace->country_code]
            ?? throw new RuntimeException("No Seller Central URL configured for {$marketplace->country_code}.");
    }
}
