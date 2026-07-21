<?php

namespace Tests\Feature;

use App\Models\AmazonAccount;
use App\Models\ChannelAccount;
use App\Models\Client;
use App\Models\Marketplace;
use App\Models\Organization;
use App\Models\User;
use App\Services\Amazon\Contracts\SellerDataProvider;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmazonMarketplaceRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_marketplace_keeps_its_own_region_not_the_connecting_accounts(): void
    {
        // A seller connecting through India (region "eu" in this app's
        // 3-bucket scheme) can still participate in the US marketplace, whose
        // real SP-API region is "na". Before this fix, syncing participations
        // stamped every returned marketplace with the connecting account's
        // own region, corrupting US/CA/MX/BR/JP/AU/SG rows the moment any
        // multi-region seller synced.
        $this->app->bind(SellerDataProvider::class, fn () => new class implements SellerDataProvider
        {
            public function authorizationUrl(string $state, Marketplace $marketplace, bool $draft = false): string
            {
                return 'https://example.test/consent';
            }

            public function exchangeAuthorizationCode(string $code): array
            {
                return [];
            }

            public function marketplaceParticipations(ChannelAccount $account): array
            {
                return [
                    ['marketplace' => ['id' => 'A21TJRUUN4KGV', 'countryCode' => 'IN', 'name' => 'Amazon India', 'defaultCurrencyCode' => 'INR', 'domainName' => 'amazon.in'], 'participation' => ['isParticipating' => true]],
                    ['marketplace' => ['id' => 'ATVPDKIKX0DER', 'countryCode' => 'US', 'name' => 'Amazon.com', 'defaultCurrencyCode' => 'USD', 'domainName' => 'amazon.com'], 'participation' => ['isParticipating' => true]],
                    ['marketplace' => ['id' => 'A1VC38T7YXB528', 'countryCode' => 'JP', 'name' => 'Amazon.co.jp', 'defaultCurrencyCode' => 'JPY', 'domainName' => 'amazon.co.jp'], 'participation' => ['isParticipating' => true]],
                ];
            }

            public function importListings(ChannelAccount $account, string $marketplaceId): iterable
            {
                return [];
            }

            public function importOrders(ChannelAccount $account, array $marketplaceIds, DateTimeInterface $updatedAfter, ?DateTimeInterface $updatedBefore = null): iterable
            {
                return [];
            }

            public function getCatalogItem(ChannelAccount $account, string $marketplaceId, string $asin): array
            {
                return [];
            }

            public function getListingItem(ChannelAccount $account, string $marketplaceId, string $sku): array
            {
                return [];
            }

            public function getProductTypeDefinition(ChannelAccount $account, string $marketplaceId, string $productType): array
            {
                return [];
            }

            public function previewListingPatch(ChannelAccount $account, string $marketplaceId, string $sku, string $productType, array $patches): array
            {
                return [];
            }

            public function publishListingPatch(ChannelAccount $account, string $marketplaceId, string $sku, string $productType, array $patches): array
            {
                return [];
            }
        });

        [, $organization, $headers] = $this->agencyUser();
        $client = Client::create([
            'organization_id' => $organization->id, 'name' => 'Region Test Client',
            'slug' => 'region-test-'.str()->random(4), 'status' => 'active',
        ]);
        Marketplace::updateOrCreate(['amazon_marketplace_id' => 'A21TJRUUN4KGV'], [
            'country_code' => 'IN', 'name' => 'Amazon India', 'currency' => 'INR', 'domain' => 'amazon.in', 'region' => 'eu',
        ]);

        // Connect via the India marketplace — account.region ends up "eu".
        $this->withHeaders($headers)->postJson('/api/v1/integrations/amazon/accounts/manual', [
            'client_id' => $client->id,
            'marketplace_id' => 'A21TJRUUN4KGV',
            'seller_id' => 'A1MULTIREGION',
            'refresh_token' => 'Atzr|IwEBIMultiRegionRefreshTokenValueLongEnough',
        ])->assertCreated();

        $account = AmazonAccount::where('account_identifier', 'A1MULTIREGION')->firstOrFail();
        $this->assertSame('eu', $account->region);

        $this->assertSame('na', Marketplace::where('amazon_marketplace_id', 'ATVPDKIKX0DER')->value('region'));
        $this->assertSame('fe', Marketplace::where('amazon_marketplace_id', 'A1VC38T7YXB528')->value('region'));
        $this->assertSame('eu', Marketplace::where('amazon_marketplace_id', 'A21TJRUUN4KGV')->value('region'));

        // The US marketplace's own name from Amazon's response is preserved,
        // not silently overwritten by anything account-specific.
        $this->assertSame('Amazon.com', Marketplace::where('amazon_marketplace_id', 'ATVPDKIKX0DER')->value('name'));
    }

    private function agencyUser(): array
    {
        $organization = Organization::create([
            'name' => 'Region Test Agency', 'slug' => 'region-test-agency-'.str()->random(5),
            'timezone' => 'Asia/Kolkata', 'currency' => 'INR',
        ]);
        $user = User::create([
            'name' => 'Owner', 'email' => 'region-test-'.str()->random(4).'@example.com',
            'password' => 'password123', 'current_organization_id' => $organization->id,
        ]);
        $organization->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $organization, [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $organization->id,
        ]];
    }
}
