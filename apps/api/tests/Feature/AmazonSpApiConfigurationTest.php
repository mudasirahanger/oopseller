<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Services\Amazon\Contracts\SellerDataProvider;
use Tests\TestCase;

class AmazonSpApiConfigurationTest extends TestCase
{
    public function test_website_authorization_url_uses_application_id_in_consent_query(): void
    {
        config()->set('services.amazon.lwa_client_id', 'amzn-client-id');
        config()->set('services.amazon.lwa_client_secret', 'secret');
        config()->set('services.amazon.application_id', 'amzn1.sellerapps.app.test');
        config()->set('services.amazon.redirect_uri', 'https://api.example.com/api/v1/integrations/amazon/callback');

        $marketplace = new Marketplace([
            'country_code' => 'IN',
            'amazon_marketplace_id' => 'A21TJRUUN4KGV',
            'name' => 'Amazon India',
            'currency' => 'INR',
            'domain' => 'amazon.in',
            'region' => 'eu',
        ]);

        $url = app(SellerDataProvider::class)->authorizationUrl('secure-state', $marketplace, true);
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $this->assertSame('/apps/authorize/consent', $parts['path']);
        $this->assertSame('amzn1.sellerapps.app.test', $query['application_id']);
        $this->assertSame('secure-state', $query['state']);
        $this->assertSame('https://api.example.com/api/v1/integrations/amazon/callback', $query['redirect_uri']);
        $this->assertSame('beta', $query['version']);
    }
}
