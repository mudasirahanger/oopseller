<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\ChannelAccount;
use App\Models\Client;
use App\Models\Listing;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Services\Channels\ChannelCatalogSyncService;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\Contracts\ChannelProvider;
use App\Services\Channels\MeeshoChannelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_exposes_docs_and_credential_fields(): void
    {
        [, , $headers] = $this->agencyUser();

        $catalog = collect($this->withHeaders($headers)->getJson('/api/v1/integrations/channels')->assertOk()->json('data'));

        $meesho = $catalog->firstWhere('platform', 'meesho');
        $this->assertSame('available', $meesho['status']);
        $this->assertNotEmpty($meesho['docs_url']);
        $this->assertNotEmpty($meesho['setup_steps']);
        $this->assertSame('api_key', $meesho['auth_type']);
        $this->assertSame('supplier_id', $meesho['credential_fields'][0]['key']);

        $flipkart = $catalog->firstWhere('platform', 'flipkart');
        $this->assertSame('needs_configuration', $flipkart['status']);
        $this->assertNotEmpty($flipkart['setup_steps']);

        $snapdeal = $catalog->firstWhere('platform', 'snapdeal');
        $this->assertSame('available', $snapdeal['status']);
    }

    public function test_meesho_api_key_connect_stores_encrypted_credentials(): void
    {
        [, $organization, $headers] = $this->agencyUser();
        $client = $this->makeClient($organization);

        $response = $this->withHeaders($headers)->postJson('/api/v1/integrations/channels/meesho/connect', [
            'client_id' => $client->id,
            'credentials' => [
                'supplier_id' => 'SUP-991',
                'api_key' => 'meesho-key',
                'api_secret' => 'meesho-secret',
            ],
        ])->assertCreated();

        $this->assertSame('meesho', $response->json('data.platform'));
        $this->assertArrayNotHasKey('credentials', $response->json('data'));

        $account = ChannelAccount::firstOrFail();
        $this->assertSame('SUP-991', $account->account_identifier);
        $this->assertSame('meesho-secret', $account->credentials['api_secret']);
        $this->assertStringNotContainsString('meesho-secret', (string) $account->getRawOriginal('credentials'));
    }

    public function test_missing_credential_fields_are_rejected(): void
    {
        [, $organization, $headers] = $this->agencyUser();
        $client = $this->makeClient($organization);

        $this->withHeaders($headers)->postJson('/api/v1/integrations/channels/snapdeal/connect', [
            'client_id' => $client->id,
            'credentials' => ['seller_code' => 'SC-1'],
        ])->assertUnprocessable();
    }

    public function test_oauth_platform_rejects_api_key_connect(): void
    {
        [, $organization, $headers] = $this->agencyUser();
        $client = $this->makeClient($organization);

        $this->withHeaders($headers)->postJson('/api/v1/integrations/channels/flipkart/connect', [
            'client_id' => $client->id,
            'credentials' => ['api_key' => 'x'],
        ])->assertUnprocessable();
    }

    public function test_unknown_platform_is_rejected(): void
    {
        [, $organization, $headers] = $this->agencyUser();
        $client = $this->makeClient($organization);

        $this->withHeaders($headers)->postJson('/api/v1/integrations/channels/etsy/connect', [
            'client_id' => $client->id,
            'credentials' => ['api_key' => 'x'],
        ])->assertNotFound();
    }

    public function test_generic_sync_persists_normalized_listings(): void
    {
        [, $organization] = $this->agencyUser();
        $client = $this->makeClient($organization);
        $account = ChannelAccount::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'platform' => 'meesho',
            'account_identifier' => 'SUP-1',
            'name' => 'Meesho SUP-1',
            'region' => 'in',
            'status' => 'active',
            'credentials' => ['supplier_id' => 'SUP-1', 'api_key' => 'k', 'api_secret' => 's'],
        ]);

        $this->app->bind(MeeshoChannelProvider::class, fn () => new class implements ChannelProvider
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

            public function exchangeCode(string $code): array
            {
                return [];
            }

            public function syncListings(ChannelAccount $account, array $options = []): iterable
            {
                yield [
                    'external_id' => 'MP-1001',
                    'sku' => 'MEESHO-SKU-1',
                    'name' => 'Cotton Kurti',
                    'status' => 'active',
                    'image_url' => null,
                    'price' => 499.0,
                    'currency' => 'INR',
                    'marketplace_code' => 'meesho_in',
                    'raw' => ['product_id' => 'MP-1001'],
                ];
            }

            public function getOrders(ChannelAccount $account, \DateTimeInterface $from, \DateTimeInterface $to, array $options = []): iterable
            {
                return [];
            }

            public function updateListing(ChannelAccount $account, Listing $listing, array $patches, array $options = []): array
            {
                return [];
            }

            public function getInventory(ChannelAccount $account, array $options = []): iterable
            {
                return [];
            }
        });

        $run = app(ChannelCatalogSyncService::class)->syncListings($account);

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->processed);

        $product = Product::firstOrFail();
        $this->assertSame('meesho', $product->platform);
        $this->assertSame('MP-1001', $product->external_id);
        $this->assertNull($product->asin);

        $listing = Listing::firstOrFail();
        $this->assertSame('meesho_in', $listing->marketplace_id);
        $this->assertSame('MEESHO-SKU-1', $listing->seller_sku);
        $this->assertSame(499.0, (float) data_get($listing->attributes, 'price'));
    }

    public function test_provider_contract_is_bound_for_new_platforms(): void
    {
        $this->assertInstanceOf(ChannelProvider::class, app(ChannelManager::class)->provider(Platform::Flipkart));
        $this->assertInstanceOf(ChannelProvider::class, app(ChannelManager::class)->provider(Platform::Snapdeal));
    }

    private function makeClient(Organization $organization): Client
    {
        return Client::create([
            'organization_id' => $organization->id,
            'name' => 'Connect Client',
            'slug' => 'connect-client-'.str()->random(4),
            'status' => 'active',
        ]);
    }

    private function agencyUser(): array
    {
        $organization = Organization::create([
            'name' => 'Connect Agency',
            'slug' => 'connect-agency-'.str()->random(5),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'connect-owner-'.str()->random(4).'@example.com',
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
