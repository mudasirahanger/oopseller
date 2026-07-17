<?php

namespace Tests\Feature;

use App\Models\AmazonAccount;
use App\Models\ChannelAccount;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_catalog_lists_amazon_and_marks_others_coming_soon(): void
    {
        [, , $headers] = $this->agencyUser();

        $response = $this->withHeaders($headers)->getJson('/api/v1/integrations/channels')->assertOk();

        $catalog = collect($response->json('data'));
        $amazon = $catalog->firstWhere('platform', 'amazon');
        $flipkart = $catalog->firstWhere('platform', 'flipkart');

        $this->assertNotNull($amazon);
        $this->assertContains($amazon['status'], ['available', 'needs_configuration']);
        $this->assertSame('coming_soon', $flipkart['status']);
        $this->assertSame('oauth', $flipkart['auth_type']);
    }

    public function test_amazon_account_is_platform_scoped_channel_account(): void
    {
        [, $organization] = $this->agencyUser();
        $client = Client::create([
            'organization_id' => $organization->id,
            'name' => 'Channel Client',
            'slug' => 'channel-client-'.str()->random(4),
            'status' => 'active',
        ]);

        $amazon = AmazonAccount::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'account_identifier' => 'SELLER123',
            'name' => 'Amazon Seller',
            'region' => 'eu',
            'status' => 'active',
        ]);
        $this->assertSame('amazon', $amazon->fresh()->platform);

        ChannelAccount::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'platform' => 'shopify',
            'account_identifier' => 'shop-42',
            'name' => 'Shopify Store',
            'region' => 'global',
            'status' => 'active',
            'credentials' => ['access_token' => 'secret-token'],
        ]);

        $this->assertSame(1, AmazonAccount::count());
        $this->assertSame(2, ChannelAccount::count());

        $shopify = ChannelAccount::where('platform', 'shopify')->first();
        $this->assertSame(['access_token' => 'secret-token'], $shopify->credentials);
        $this->assertArrayNotHasKey('credentials', $shopify->toArray());
    }

    private function agencyUser(): array
    {
        $organization = Organization::create([
            'name' => 'Channel Agency',
            'slug' => 'channel-agency-'.str()->random(5),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'channel-owner@example.com',
            'password' => 'password123',
            'current_organization_id' => $organization->id,
        ]);
        $organization->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $organization, [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $organization->id,
        ]];
    }
}
