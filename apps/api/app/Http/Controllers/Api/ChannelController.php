<?php

namespace App\Http\Controllers\Api;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Jobs\Channels\SyncChannelListings;
use App\Jobs\Channels\SyncChannelOrders;
use App\Models\ChannelAccount;
use App\Models\ChannelSyncRun;
use App\Models\Client;
use App\Services\Channels\ChannelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ChannelController extends Controller
{
    public function index(Request $request, ChannelManager $channels): JsonResponse
    {
        $organizationId = (int) $request->attributes->get('organization_id');

        $accountCounts = ChannelAccount::query()
            ->where('organization_id', $organizationId)
            ->selectRaw('platform, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active', ['active'])
            ->groupBy('platform')
            ->get()
            ->keyBy('platform');

        $catalog = array_map(function (array $entry) use ($accountCounts): array {
            $counts = $accountCounts->get($entry['platform']);

            return [
                ...$entry,
                'accounts_total' => (int) ($counts->total ?? 0),
                'accounts_active' => (int) ($counts->active ?? 0),
            ];
        }, $channels->catalog());

        return response()->json(['data' => $catalog]);
    }

    public function accounts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ChannelAccount::query()
                ->where('organization_id', (int) $request->attributes->get('organization_id'))
                ->where('platform', '!=', Platform::Amazon->value)
                ->with(['client:id,name'])
                ->with(['syncRuns' => fn ($query) => $query->latest()->limit(5)])
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Connect an API-key platform (Meesho, Snapdeal, ...) by storing the
     * per-account credentials encrypted on the channel account.
     */
    public function connect(Request $request, string $platform, ChannelManager $channels): JsonResponse
    {
        $enum = $this->resolvePlatform($platform, $channels);
        abort_unless($enum->authType() === 'api_key', 422, "{$enum->label()} uses OAuth — start the authorization flow instead.");

        $fields = collect($enum->credentialFields());
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'credentials' => ['required', 'array'],
            ...$fields->mapWithKeys(fn (array $field) => [
                'credentials.'.$field['key'] => ['required', 'string', 'max:500'],
            ])->all(),
        ]);

        $organizationId = (int) $request->attributes->get('organization_id');
        $client = Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        $identifier = (string) ($data['credentials'][$fields->first()['key']] ?? Str::random(10));

        $account = ChannelAccount::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'platform' => $enum->value,
                'account_identifier' => $identifier,
            ],
            [
                'client_id' => $client->id,
                'name' => ($data['name'] ?? null) ?: $enum->label().' '.$identifier,
                'region' => 'in',
                'credentials' => collect($data['credentials'])->only($fields->pluck('key'))->all(),
                'status' => 'active',
                'authorized_at' => now(),
                'last_sync_error' => null,
            ],
        );

        return response()->json(['data' => $account->load('client:id,name')], 201);
    }

    /**
     * Start the OAuth flow for OAuth platforms (currently Flipkart).
     */
    public function authorize(Request $request, string $platform, ChannelManager $channels): JsonResponse
    {
        $enum = $this->resolvePlatform($platform, $channels);
        abort_unless($enum->authType() === 'oauth', 422, "{$enum->label()} uses API keys — use the connect endpoint instead.");
        abort_if($enum === Platform::Amazon, 422, 'Use the dedicated Amazon authorization endpoint.');

        $data = $request->validate(['client_id' => ['required', 'integer', 'exists:clients,id']]);
        $organizationId = (int) $request->attributes->get('organization_id');
        $client = Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        $state = Str::random(64);

        Cache::put('channel_oauth_state:'.$enum->value.':'.$state, [
            'organization_id' => $organizationId,
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
        ], now()->addMinutes(15));

        $url = $channels->provider($enum)->authorizationUrl($state);
        abort_unless($url, 422, "{$enum->label()} did not produce an authorization URL. Check the server configuration.");

        return response()->json(['data' => ['authorization_url' => $url]]);
    }

    public function callback(Request $request, string $platform, ChannelManager $channels): RedirectResponse
    {
        $enum = $this->resolvePlatform($platform, $channels);

        if ($request->filled('error')) {
            return $this->frontendRedirect([
                'channel' => $enum->value,
                'status' => 'error',
                'message' => Str::limit((string) ($request->input('error_description') ?: $request->input('error')), 180),
            ]);
        }

        $data = $request->validate([
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);
        $state = Cache::pull('channel_oauth_state:'.$enum->value.':'.$data['state']);
        abort_unless($state, 419, 'Authorization state expired or is invalid.');

        try {
            $token = $channels->provider($enum)->exchangeCode($data['code']);
            $identifier = (string) ($token['seller_id'] ?? $token['merchant_id'] ?? 'seller-'.Str::lower(Str::random(8)));

            $account = ChannelAccount::updateOrCreate(
                [
                    'organization_id' => $state['organization_id'],
                    'platform' => $enum->value,
                    'account_identifier' => $identifier,
                ],
                [
                    'client_id' => $state['client_id'],
                    'name' => $enum->label().' Seller '.$identifier,
                    'region' => 'in',
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'credentials' => ['access_token' => $token['access_token'] ?? null],
                    'status' => 'active',
                    'authorized_at' => now(),
                    'last_sync_error' => null,
                ],
            );

            $this->queueSync($account);

            return $this->frontendRedirect(['channel' => $enum->value, 'status' => 'connected', 'account_id' => $account->id]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->frontendRedirect([
                'channel' => $enum->value,
                'status' => 'error',
                'message' => "{$enum->label()} authorization failed. Try connecting the seller account again.",
            ]);
        }
    }

    public function syncOrders(Request $request, ChannelAccount $channelAccount): JsonResponse
    {
        $this->assertAccountAccess($request, $channelAccount);
        abort_unless($channelAccount->status === 'active', 422, 'This channel account is not active.');

        $pending = ChannelSyncRun::query()
            ->where('channel_account_id', $channelAccount->id)
            ->where('type', 'orders')
            ->whereIn('status', ['queued', 'running'])
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if ($pending) {
            return response()->json(['data' => $pending], 202);
        }

        $run = ChannelSyncRun::create([
            'organization_id' => $channelAccount->organization_id,
            'client_id' => $channelAccount->client_id,
            'platform' => $channelAccount->platform,
            'channel_account_id' => $channelAccount->id,
            'marketplace_id' => $channelAccount->platform,
            'type' => 'orders',
            'status' => 'queued',
        ]);

        try {
            SyncChannelOrders::dispatch($channelAccount->id, $run->id);
        } catch (Throwable $exception) {
            // See AmazonIntegrationController::queueSync().
            report($exception);
        }

        return response()->json(['data' => $run->fresh()], 202);
    }

    public function sync(Request $request, ChannelAccount $channelAccount): JsonResponse
    {
        $this->assertAccountAccess($request, $channelAccount);
        abort_if($channelAccount->platform === Platform::Amazon->value, 422, 'Use the Amazon sync endpoint for Amazon accounts.');
        abort_unless($channelAccount->status === 'active', 422, 'This channel account is not active.');

        return response()->json(['data' => $this->queueSync($channelAccount)], 202);
    }

    public function disconnect(Request $request, ChannelAccount $channelAccount): JsonResponse
    {
        $this->assertAccountAccess($request, $channelAccount);
        $this->requireOrganizationRole($request, 'owner', 'admin');
        $channelAccount->update([
            'refresh_token' => null,
            'credentials' => null,
            'status' => 'disconnected',
            'last_sync_error' => null,
        ]);

        return response()->json(['message' => 'Channel account disconnected.']);
    }

    private function queueSync(ChannelAccount $account): ChannelSyncRun
    {
        $pending = ChannelSyncRun::query()
            ->where('channel_account_id', $account->id)
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
            'marketplace_id' => $account->platform.'_in',
            'type' => 'listings',
            'status' => 'queued',
        ]);

        try {
            SyncChannelListings::dispatch($account->id, $run->id);
        } catch (Throwable $exception) {
            // See AmazonIntegrationController::queueSync() — under
            // QUEUE_CONNECTION=sync the job runs inline and re-throws on
            // failure after already marking $run failed; don't let that
            // crash whichever request triggered the sync.
            report($exception);
        }

        return $run->fresh();
    }

    private function resolvePlatform(string $platform, ChannelManager $channels): Platform
    {
        $enum = Platform::tryFrom($platform);
        abort_unless($enum, 404, 'Unknown platform.');
        abort_unless($channels->has($enum), 422, "{$enum->label()} is not integrated yet.");

        return $enum;
    }

    private function assertAccountAccess(Request $request, ChannelAccount $account): void
    {
        abort_unless($account->organization_id === (int) $request->attributes->get('organization_id'), 404);
    }

    private function frontendRedirect(array $query): RedirectResponse
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/integrations';

        return redirect($url.'?'.http_build_query($query));
    }
}
