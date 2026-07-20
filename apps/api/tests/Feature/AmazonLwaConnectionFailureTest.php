<?php

namespace Tests\Feature;

use App\Services\Amazon\AmazonLwaClient;
use App\Services\Amazon\Exceptions\AmazonSpApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonLwaConnectionFailureTest extends TestCase
{
    public function test_a_connection_failure_talking_to_amazon_is_converted_to_amazon_sp_api_exception(): void
    {
        config()->set('services.amazon.lwa_client_id', 'client-id');
        config()->set('services.amazon.lwa_client_secret', 'client-secret');
        config()->set('services.amazon.application_id', 'app-id');
        config()->set('services.amazon.redirect_uri', 'https://api.example.com/callback');

        Http::fake(function (): void {
            throw new ConnectionException('cURL error 6: Could not resolve host');
        });

        $this->expectException(AmazonSpApiException::class);
        $this->expectExceptionMessage("Could not reach Amazon's authorization servers");

        app(AmazonLwaClient::class)->exchangeAuthorizationCode('some-code');
    }
}
