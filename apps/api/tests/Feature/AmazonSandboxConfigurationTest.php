<?php

namespace Tests\Feature;

use App\Services\Amazon\AmazonConfiguration;
use Tests\TestCase;

class AmazonSandboxConfigurationTest extends TestCase
{
    public function test_endpoint_uses_sandbox_host_only_when_explicitly_requested(): void
    {
        $configuration = new AmazonConfiguration;

        $this->assertSame('https://sellingpartnerapi-eu.amazon.com', $configuration->endpoint('eu'));
        $this->assertSame('https://sellingpartnerapi-eu.amazon.com', $configuration->endpoint('eu', false));
        $this->assertSame('https://sandbox.sellingpartnerapi-eu.amazon.com', $configuration->endpoint('eu', true));
        $this->assertSame('https://sandbox.sellingpartnerapi-na.amazon.com', $configuration->endpoint('na', true));
    }
}
