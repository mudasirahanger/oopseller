<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Amazon\SyncAmazonListings;
use App\Models\AmazonAccount;
use App\Models\ChannelSyncRun;
use App\Models\Client;
use App\Models\Marketplace;
use App\Services\Amazon\AmazonConfiguration;
use App\Services\Amazon\Contracts\SellerDataProvider;
use App\Services\Amazon\Exceptions\AmazonSpApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class AmazonIntegrationController extends Controller
{
    public function __construct(private readonly AmazonConfiguration $configuration) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (int) $request->attributes->get('organization_id');

        return response()->json([
            'data' => AmazonAccount::query()
                ->where('organization_id', $organizationId)
                ->with(['client:id,name', 'marketplaces'])
                ->with(['syncRuns' => fn ($query) => $query->latest()->limit(5)])
                ->latest()
                ->get(),
            'meta' => [
                'configured' => $this->isConfigured(),
                'draft_mode' => (bool) config('services.amazon.authorization_draft'),
                'redirect_uri' => config('services.amazon.redirect_uri'),
                'sandbox_default' => (bool) config('services.amazon.sandbox'),
            ],
        ]);
    }

    public function marketplaces(): JsonResponse
    {
        return response()->json(['data' => Marketplace::query()->orderBy('name')->get()]);
    }

    public function authorizeSeller(Request $request, SellerDataProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'marketplace_id' => ['required', 'string', 'exists:marketplaces,amazon_marketplace_id'],
            'draft' => ['sometimes', 'boolean'],
            'sandbox' => ['sometimes', 'boolean'],
        ]);
        $organizationId = (int) $request->attributes->get('organization_id');
        $client = Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        $marketplace = Marketplace::where('amazon_marketplace_id', $data['marketplace_id'])->firstOrFail();
        $state = Str::random(64);

        Cache::put('amazon_oauth_state:'.$state, [
            'organization_id' => $organizationId,
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'marketplace_id' => $marketplace->amazon_marketplace_id,
            'region' => $marketplace->region,
            'sandbox' => (bool) ($data['sandbox'] ?? config('services.amazon.sandbox')),
        ], now()->addMinutes(15));

        return response()->json([
            'data' => [
                'authorization_url' => $provider->authorizationUrl(
                    $state,
                    $marketplace,
                    (bool) ($data['draft'] ?? config('services.amazon.authorization_draft')),
                ),
            ],
        ]);
    }

    public function callback(Request $request, SellerDataProvider $provider): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->frontendRedirect([
                'amazon' => 'error',
                'message' => Str::limit((string) ($request->input('error_description') ?: $request->input('error')), 180),
            ]);
        }

        $data = $request->validate([
            'state' => ['required', 'string'],
            'spapi_oauth_code' => ['required', 'string'],
            'selling_partner_id' => ['required', 'string', 'max:100'],
        ]);
        $state = Cache::pull('amazon_oauth_state:'.$data['state']);

        abort_unless($state, 419, 'Amazon authorization state expired or is invalid.');

        try {
            $token = $provider->exchangeAuthorizationCode($data['spapi_oauth_code']);
            abort_unless(filled($token['refresh_token'] ?? null), 502, 'Amazon did not return a refresh token. Restart the authorization flow.');

            $existingAccount = AmazonAccount::query()
                ->where('organization_id', $state['organization_id'])
                ->where('account_identifier', $data['selling_partner_id'])
                ->first();
            abort_if(
                $existingAccount && $existingAccount->client_id !== (int) $state['client_id'],
                409,
                'This Amazon seller account is already connected to another client in the agency.',
            );

            $account = AmazonAccount::updateOrCreate(
                [
                    'organization_id' => $state['organization_id'],
                    'account_identifier' => $data['selling_partner_id'],
                ],
                [
                    'client_id' => $state['client_id'],
                    'name' => 'Amazon Seller '.$data['selling_partner_id'],
                    'region' => $state['region'],
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'status' => 'active',
                    'authorized_at' => now(),
                    'last_sync_error' => null,
                    'metadata' => ['initial_marketplace_id' => $state['marketplace_id'], 'sandbox' => (bool) ($state['sandbox'] ?? false)],
                ],
            );

            Cache::forget('amazon_lwa_access_token:'.$account->id);
            $this->syncMarketplaceParticipations($account, $provider, $state['marketplace_id']);
            $this->queueSync($account, $state['marketplace_id']);

            return $this->frontendRedirect(['amazon' => 'connected', 'account_id' => $account->id]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->frontendRedirect([
                'amazon' => 'error',
                'message' => $exception instanceof AmazonSpApiException
                    ? Str::limit($exception->getMessage(), 180)
                    : 'Amazon authorization failed. Try connecting the seller account again.',
            ]);
        }
    }

    /**
     * Connect an Amazon seller account by pasting a refresh token obtained
     * through Amazon's direct self-authorization (Seller Central > Manage
     * Your Apps > Authorize), used for Private SP-API applications, which
     * have no OAuth Login/Redirect URI and cannot use the redirect-based
     * authorizeSeller()/callback() flow above (that flow requires a Public
     * application; using it on a Private application is what produces
     * Amazon error MD9100).
     */
    public function connectManually(Request $request, SellerDataProvider $provider): JsonResponse
    {
        $this->requireOrganizationRole($request, 'owner', 'admin');
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'marketplace_id' => ['required', 'string', 'exists:marketplaces,amazon_marketplace_id'],
            // A real Amazon Selling Partner ID is a short code (e.g.
            // "A1B2C3D4E5F6G7"). It is easy to mistake the Application ID
            // (e.g. "amzn1.sp.solution....") shown on the same Developer
            // Console page for it — that mistake passes connect (which never
            // sends this value to Amazon) but then fails every listings/orders
            // call with a cryptic "Could not match input arguments", since
            // that URL embeds this value as the seller ID.
            'seller_id' => [
                'required', 'string', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (str_starts_with(strtolower((string) $value), 'amzn1.')) {
                        $fail('This looks like an Application ID, not a Selling Partner (seller) ID. Find the correct seller ID on the same Seller Central "Authorize" screen where the refresh token was shown.');
                    }
                },
            ],
            'refresh_token' => ['required', 'string', 'min:20'],
            'sandbox' => ['sometimes', 'boolean'],
        ]);
        $organizationId = (int) $request->attributes->get('organization_id');
        $client = Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        $marketplace = Marketplace::where('amazon_marketplace_id', $data['marketplace_id'])->firstOrFail();

        $existingAccount = AmazonAccount::query()
            ->where('organization_id', $organizationId)
            ->where('account_identifier', $data['seller_id'])
            ->first();
        abort_if(
            $existingAccount && $existingAccount->client_id !== $client->id,
            409,
            'This Amazon seller account is already connected to another client in the agency.',
        );

        $account = AmazonAccount::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'account_identifier' => $data['seller_id'],
            ],
            [
                'client_id' => $client->id,
                'name' => 'Amazon Seller '.$data['seller_id'],
                'region' => $marketplace->region,
                'refresh_token' => $data['refresh_token'],
                'status' => 'active',
                'authorized_at' => now(),
                'last_sync_error' => null,
                'metadata' => [
                    'initial_marketplace_id' => $marketplace->amazon_marketplace_id,
                    'connected_via' => 'manual_refresh_token',
                    'sandbox' => (bool) ($data['sandbox'] ?? config('services.amazon.sandbox')),
                ],
            ],
        );
        Cache::forget('amazon_lwa_access_token:'.$account->id);

        try {
            $provider->marketplaceParticipations($account);
        } catch (AmazonSpApiException $exception) {
            $wasNew = $existingAccount === null;
            if ($wasNew) {
                $account->delete();
            } else {
                $account->update(['status' => 'error', 'last_sync_error' => $exception->getMessage()]);
            }
            report($exception);

            return response()->json([
                'message' => 'Amazon rejected this refresh token: '.Str::limit($exception->getMessage(), 200),
            ], 422);
        }

        $this->syncMarketplaceParticipations($account, $provider, $marketplace->amazon_marketplace_id);
        $this->queueSync($account, $marketplace->amazon_marketplace_id);

        return response()->json(['data' => $account->fresh()->load('client:id,name', 'marketplaces')], 201);
    }

    public function sync(Request $request, AmazonAccount $amazonAccount): JsonResponse
    {
        $this->assertAccountAccess($request, $amazonAccount);
        $data = $request->validate([
            'marketplace_id' => ['required', 'string', 'exists:marketplaces,amazon_marketplace_id'],
        ]);
        abort_unless(
            $amazonAccount->marketplaces()->where('amazon_marketplace_id', $data['marketplace_id'])->wherePivot('enabled', true)->exists(),
            422,
            'This marketplace is not enabled for the Amazon account.',
        );

        $run = $this->queueSync($amazonAccount, $data['marketplace_id']);

        return response()->json(['data' => $run], 202);
    }

    public function disconnect(Request $request, AmazonAccount $amazonAccount): JsonResponse
    {
        $this->assertAccountAccess($request, $amazonAccount);
        $this->requireOrganizationRole($request, 'owner', 'admin');
        $amazonAccount->update([
            'refresh_token' => null,
            'status' => 'disconnected',
            'last_sync_error' => null,
        ]);
        Cache::forget('amazon_lwa_access_token:'.$amazonAccount->id);

        return response()->json(['message' => 'Amazon seller account disconnected.']);
    }

    private function syncMarketplaceParticipations(AmazonAccount $account, SellerDataProvider $provider, string $fallbackMarketplaceId): void
    {
        try {
            $participations = $provider->marketplaceParticipations($account);
            $ids = [];

            foreach ($participations as $participation) {
                $marketplaceData = $participation['marketplace'] ?? [];
                $marketplaceId = $marketplaceData['id'] ?? null;

                if (! $marketplaceId) {
                    continue;
                }

                $countryCode = $marketplaceData['countryCode'] ?? 'XX';
                $marketplace = Marketplace::updateOrCreate(
                    ['amazon_marketplace_id' => $marketplaceId],
                    [
                        'country_code' => $countryCode,
                        'name' => $marketplaceData['name'] ?? $marketplaceId,
                        'currency' => $marketplaceData['defaultCurrencyCode'] ?? 'USD',
                        'domain' => $marketplaceData['domainName'] ?? 'amazon.com',
                        // Each marketplace has its own SP-API region — a
                        // seller can participate in several at once, so this
                        // must not default to the connecting account's region.
                        'region' => $this->configuration->regionForCountryCode($countryCode, $account->region),
                    ],
                );
                $ids[$marketplace->id] = ['enabled' => (bool) data_get($participation, 'participation.isParticipating', true)];
            }

            $fallback = Marketplace::where('amazon_marketplace_id', $fallbackMarketplaceId)->first();
            if ($fallback && ! isset($ids[$fallback->id])) {
                $ids[$fallback->id] = ['enabled' => true];
            }

            if ($ids !== []) {
                $account->marketplaces()->sync($ids);

                return;
            }
        } catch (AmazonSpApiException $exception) {
            report($exception);
        }

        $fallback = Marketplace::where('amazon_marketplace_id', $fallbackMarketplaceId)->first();
        if ($fallback) {
            $account->marketplaces()->syncWithoutDetaching([$fallback->id => ['enabled' => true]]);
        }
    }

    private function queueSync(AmazonAccount $account, string $marketplaceId): ChannelSyncRun
    {
        $pending = ChannelSyncRun::query()
            ->where('channel_account_id', $account->id)
            ->where('marketplace_id', $marketplaceId)
            ->whereIn('status', ['queued', 'running'])
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if ($pending) {
            return $pending;
        }

        $run = ChannelSyncRun::create([
            'organization_id' => $account->organization_id,
            'client_id' => $account->client_id,
            'platform' => $account->platform,
            'channel_account_id' => $account->id,
            'marketplace_id' => $marketplaceId,
            'type' => 'listings',
            'status' => 'queued',
        ]);

        try {
            SyncAmazonListings::dispatch($account->id, $marketplaceId, $run->id);
        } catch (Throwable $exception) {
            // On QUEUE_CONNECTION=sync, dispatch() runs the job inline and
            // AmazonCatalogSyncService re-throws on failure so a real queue
            // worker can retry it; it already marks $run failed before doing
            // so. Swallow it here so a sync failure doesn't crash whichever
            // request triggered it (e.g. right after connecting an account).
            report($exception);
        }

        return $run->fresh();
    }

    private function assertAccountAccess(Request $request, AmazonAccount $account): void
    {
        abort_unless($account->organization_id === (int) $request->attributes->get('organization_id'), 404);
    }

    private function frontendRedirect(array $query): RedirectResponse
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/integrations';

        return redirect($url.'?'.http_build_query($query));
    }

    private function isConfigured(): bool
    {
        return filled(config('services.amazon.lwa_client_id'))
            && filled(config('services.amazon.lwa_client_secret'))
            && filled(config('services.amazon.application_id'))
            && filled(config('services.amazon.redirect_uri'));
    }
}
