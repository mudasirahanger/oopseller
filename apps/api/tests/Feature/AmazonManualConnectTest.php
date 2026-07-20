<?php

namespace Tests\Feature;

use App\Models\AmazonAccount;
use App\Models\ChannelAccount;
use App\Models\Client;
use App\Models\Marketplace;
use App\Models\Organization;
use App\Models\User;
use App\Services\Amazon\Contracts\SellerDataProvider;
use App\Services\Amazon\Exceptions\AmazonSpApiException;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmazonManualConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_connect_amazon_account_with_a_valid_refresh_token(): void
    {
        $this->bindStubProvider(shouldFail: false);
        [, $organization, $headers] = $this->agencyUser('owner');
        $client = $this->makeClient($organization);
        $this->seedMarketplace();

        $response = $this->withHeaders($headers)->postJson('/api/v1/integrations/amazon/accounts/manual', [
            'client_id' => $client->id,
            'marketplace_id' => 'A21TJRUUN4KGV',
            'seller_id' => 'A1SELLERTEST',
            'refresh_token' => 'Atzr|IwEBIExampleRefreshTokenValueLongEnough',
        ])->assertCreated();

        $this->assertSame('active', $response->json('data.status'));
        $account = AmazonAccount::firstOrFail();
        $this->assertSame('A1SELLERTEST', $account->account_identifier);
        $this->assertSame('manual_refresh_token', $account->metadata['connected_via']);
        $this->assertSame('Atzr|IwEBIExampleRefreshTokenValueLongEnough', $account->refresh_token);
        $this->assertFalse($account->metadata['sandbox']);
    }

    public function test_sandbox_flag_is_per_account_not_global(): void
    {
        $this->bindStubProvider(shouldFail: false);
        [, $organization, $headers] = $this->agencyUser('owner');
        $client = $this->makeClient($organization);
        $this->seedMarketplace();

        $this->withHeaders($headers)->postJson('/api/v1/integrations/amazon/accounts/manual', [
            'client_id' => $client->id,
            'marketplace_id' => 'A21TJRUUN4KGV',
            'seller_id' => 'A1SANDBOXTEST',
            'refresh_token' => 'Atzr|IwEBISandboxRefreshTokenValueLongEnough',
            'sandbox' => true,
        ])->assertCreated();

        $sandboxAccount = AmazonAccount::where('account_identifier', 'A1SANDBOXTEST')->firstOrFail();
        $this->assertTrue($sandboxAccount->metadata['sandbox']);

        $this->withHeaders($headers)->postJson('/api/v1/integrations/amazon/accounts/manual', [
            'client_id' => $client->id,
            'marketplace_id' => 'A21TJRUUN4KGV',
            'seller_id' => 'A1LIVETEST',
            'refresh_token' => 'Atzr|IwEBILiveRefreshTokenValueLongEnough',
        ])->assertCreated();

        $liveAccount = AmazonAccount::where('account_identifier', 'A1LIVETEST')->firstOrFail();
        $this->assertFalse($liveAccount->metadata['sandbox']);
    }

    public function test_invalid_refresh_token_is_rejected_and_no_account_is_left_behind(): void
    {
        $this->bindStubProvider(shouldFail: true);
        [, $organization, $headers] = $this->agencyUser('owner');
        $client = $this->makeClient($organization);
        $this->seedMarketplace();

        $this->withHeaders($headers)->postJson('/api/v1/integrations/amazon/accounts/manual', [
            'client_id' => $client->id,
            'marketplace_id' => 'A21TJRUUN4KGV',
            'seller_id' => 'A1BADTOKEN',
            'refresh_token' => 'not-a-real-refresh-token-value',
        ])->assertUnprocessable();

        $this->assertSame(0, AmazonAccount::count());
    }

    public function test_member_role_cannot_connect_manually(): void
    {
        $this->bindStubProvider(shouldFail: false);
        [, $organization] = $this->agencyUser('owner');
        $client = $this->makeClient($organization);
        $this->seedMarketplace();

        $member = User::create([
            'name' => 'Member', 'email' => 'manual-member@example.com',
            'password' => 'password123', 'current_organization_id' => $organization->id,
        ]);
        $organization->users()->attach($member->id, ['role' => 'member']);
        $memberHeaders = [
            'Authorization' => 'Bearer '.$member->createToken('t')->plainTextToken,
            'X-Organization-Id' => (string) $organization->id,
        ];

        $this->withHeaders($memberHeaders)->postJson('/api/v1/integrations/amazon/accounts/manual', [
            'client_id' => $client->id,
            'marketplace_id' => 'A21TJRUUN4KGV',
            'seller_id' => 'A1SELLERTEST',
            'refresh_token' => 'Atzr|IwEBIExampleRefreshTokenValueLongEnough',
        ])->assertForbidden();

        $this->assertSame(0, AmazonAccount::count());
    }

    private function bindStubProvider(bool $shouldFail): void
    {
        $this->app->bind(SellerDataProvider::class, fn () => new class($shouldFail) implements SellerDataProvider
        {
            public function __construct(private readonly bool $shouldFail) {}

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
                if ($this->shouldFail) {
                    throw new AmazonSpApiException('Invalid refresh token.', 401);
                }

                return [];
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
    }

    private function seedMarketplace(): void
    {
        Marketplace::updateOrCreate(['amazon_marketplace_id' => 'A21TJRUUN4KGV'], [
            'country_code' => 'IN', 'name' => 'Amazon India', 'currency' => 'INR',
            'domain' => 'amazon.in', 'region' => 'eu',
        ]);
    }

    private function makeClient(Organization $organization): Client
    {
        return Client::create([
            'organization_id' => $organization->id,
            'name' => 'Manual Connect Client',
            'slug' => 'manual-connect-client-'.str()->random(4),
            'status' => 'active',
        ]);
    }

    private function agencyUser(string $role): array
    {
        $organization = Organization::create([
            'name' => 'Manual Connect Agency',
            'slug' => 'manual-connect-agency-'.str()->random(5),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);
        $user = User::create([
            'name' => 'Owner', 'email' => 'manual-owner-'.str()->random(4).'@example.com',
            'password' => 'password123', 'current_organization_id' => $organization->id,
        ]);
        $organization->users()->attach($user->id, ['role' => $role]);

        return [$user, $organization, [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $organization->id,
        ]];
    }
}
