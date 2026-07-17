<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingVersion;
use App\Services\Amazon\AmazonCatalogSyncService;
use App\Services\Amazon\AmazonListingPatchBuilder;
use App\Services\Amazon\AmazonProductTypeDefinitionService;
use App\Services\Amazon\Contracts\SellerDataProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Listing::query()
            ->where('organization_id', $request->attributes->get('organization_id'))
            ->with(['product:id,asin,sku,name,product_type,image_url', 'client:id,name', 'channelAccount:id,name,platform,status']);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('search')) {
            $search = trim(str_replace(['%', '_'], ' ', (string) $request->input('search')));
            $query->where(fn ($builder) => $builder
                ->where('seller_sku', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($product) => $product->where('asin', 'like', "%{$search}%")));
        }

        return response()->json($query->latest()->paginate(min(100, max(1, $request->integer('per_page', 30)))));
    }

    public function show(Request $request, Listing $listing): JsonResponse
    {
        $this->assertAccess($request, $listing);

        return response()->json(['data' => $listing->load([
            'product', 'client', 'channelAccount', 'versions.creator',
            'audits' => fn ($query) => $query->latest('audited_at'),
        ])]);
    }

    public function update(Request $request, Listing $listing): JsonResponse
    {
        $this->assertAccess($request, $listing);
        $data = $this->validateContent($request);
        $content = $this->contentOnly($data);

        abort_if($content === [], 422, 'No listing content was provided.');

        DB::transaction(function () use ($request, $listing, $content, $data): void {
            ListingVersion::create([
                'organization_id' => $listing->organization_id,
                'client_id' => $listing->client_id,
                'listing_id' => $listing->id,
                'created_by' => $request->user()->id,
                'version' => ($listing->versions()->max('version') ?? 0) + 1,
                'source' => 'manual',
                'content' => $content,
                'target_keywords' => $data['target_keywords'] ?? [],
                'change_summary' => $data['change_summary'] ?? null,
            ]);
            $listing->update($content);
        });

        return response()->json(['data' => $listing->fresh(['product', 'versions'])]);
    }

    public function refreshAmazon(Request $request, Listing $listing, AmazonCatalogSyncService $amazon): JsonResponse
    {
        $this->assertAccess($request, $listing);
        abort_unless($listing->channel_account_id && $listing->seller_sku, 422, 'Connect an Amazon account and seller SKU first.');

        $product = $amazon->refreshProduct($listing->product, $listing->marketplace_id, $listing->seller_sku);

        return response()->json(['data' => $product->listings()->whereKey($listing->id)->firstOrFail()->load('product')]);
    }

    public function previewAmazon(
        Request $request,
        Listing $listing,
        SellerDataProvider $provider,
        AmazonListingPatchBuilder $patchBuilder,
        AmazonProductTypeDefinitionService $productTypes,
    ): JsonResponse {
        $this->assertAccess($request, $listing);
        $account = $listing->amazonAccount;
        abort_unless($account && $account->status === 'active' && $listing->seller_sku, 422, 'This listing is not connected to an active Amazon seller account.');

        $data = $this->validateContent($request);
        $content = $this->contentOnly($data);
        $patches = $patchBuilder->build($content, $listing->marketplace_id);
        abort_if($patches === [], 422, 'No supported Amazon listing attributes were provided.');

        $productType = $this->productType($listing);
        $definition = $productTypes->definition($account, $listing->marketplace_id, $productType);
        $propertyGroupWarnings = $productTypes->unsupportedPatchAttributes($definition, $patches);
        $response = $provider->previewListingPatch(
            $account,
            $listing->marketplace_id,
            $listing->seller_sku,
            $productType,
            $patches,
        );
        $hasErrors = collect($response['issues'] ?? [])->contains(
            fn (array $issue) => strtoupper((string) ($issue['severity'] ?? '')) === 'ERROR',
        );
        $previewValid = strtoupper((string) ($response['status'] ?? '')) === 'VALID' && ! $hasErrors;
        $hash = $this->previewHash($listing, $productType, $patches);

        if ($previewValid) {
            Cache::put($this->previewCacheKey($listing, $hash), [
                'product_type' => $productType,
                'patches' => $patches,
                'content' => $content,
                'preview_response' => $response,
            ], now()->addMinutes(15));
        }

        return response()->json([
            'data' => [
                'can_publish' => $previewValid,
                'preview_hash' => $hash,
                'amazon_response' => $response,
                'patches' => $patches,
                'product_type_definition' => [
                    'productType' => $definition['productType'] ?? $productType,
                    'displayName' => $definition['displayName'] ?? null,
                    'version' => data_get($definition, 'productTypeVersion.version'),
                    'latest' => data_get($definition, 'productTypeVersion.latest'),
                    'requirements' => $definition['requirements'] ?? null,
                    'requirementsEnforced' => $definition['requirementsEnforced'] ?? null,
                    'property_group_warnings' => $propertyGroupWarnings,
                ],
            ],
        ]);
    }

    public function publishAmazon(Request $request, Listing $listing, SellerDataProvider $provider): JsonResponse
    {
        $this->assertAccess($request, $listing);
        $data = $request->validate([
            'preview_hash' => ['required', 'string', 'size:64'],
            'confirm' => ['accepted'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
        ]);
        $account = $listing->amazonAccount;
        abort_unless($account && $account->status === 'active' && $listing->seller_sku, 422, 'This listing is not connected to an active Amazon seller account.');

        $preview = Cache::pull($this->previewCacheKey($listing, $data['preview_hash']));
        abort_unless($preview, 422, 'A successful Amazon validation preview is required within the last 15 minutes.');

        $response = $provider->publishListingPatch(
            $account,
            $listing->marketplace_id,
            $listing->seller_sku,
            $preview['product_type'],
            $preview['patches'],
        );

        abort_unless(
            strtoupper((string) ($response['status'] ?? '')) === 'ACCEPTED',
            422,
            collect($response['issues'] ?? [])->pluck('message')->filter()->join(' ') ?: 'Amazon did not accept the listing update.',
        );

        DB::transaction(function () use ($request, $listing, $preview, $data): void {
            ListingVersion::create([
                'organization_id' => $listing->organization_id,
                'client_id' => $listing->client_id,
                'listing_id' => $listing->id,
                'created_by' => $request->user()->id,
                'version' => ($listing->versions()->max('version') ?? 0) + 1,
                'source' => 'amazon_publish',
                'content' => $preview['content'],
                'target_keywords' => [],
                'change_summary' => $data['change_summary'] ?? 'Published to Amazon after validation preview.',
                'published_at' => now(),
            ]);
            $listing->update([
                ...$preview['content'],
                'last_published_at' => now(),
                'last_sync_error' => null,
            ]);
        });

        return response()->json(['data' => ['listing' => $listing->fresh(), 'amazon_response' => $response]]);
    }

    private function validateContent(Request $request): array
    {
        return $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:500'],
            'bullet_points' => ['sometimes', 'array', 'max:10'],
            'bullet_points.*' => ['string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'backend_terms' => ['sometimes', 'array', 'max:20'],
            'backend_terms.*' => ['string', 'max:500'],
            'attributes' => ['sometimes', 'array'],
            'image_count' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'a_plus_status' => ['sometimes', 'in:not_eligible,not_started,draft,submitted,published,rejected'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
            'target_keywords' => ['nullable', 'array', 'max:100'],
            'target_keywords.*' => ['string', 'max:250'],
        ]);
    }

    private function contentOnly(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'title', 'bullet_points', 'description', 'backend_terms',
            'attributes', 'image_count', 'a_plus_status',
        ]));
    }

    private function productType(Listing $listing): string
    {
        $type = $listing->product->product_type
            ?: data_get($listing->product_types, '0.productType');

        abort_unless($type, 422, 'Amazon product type is missing. Refresh the listing from Amazon first.');

        return $type;
    }

    private function previewHash(Listing $listing, string $productType, array $patches): string
    {
        return hash('sha256', json_encode([
            'listing_id' => $listing->id,
            'seller_sku' => $listing->seller_sku,
            'marketplace_id' => $listing->marketplace_id,
            'product_type' => $productType,
            'patches' => $patches,
        ], JSON_THROW_ON_ERROR));
    }

    private function previewCacheKey(Listing $listing, string $hash): string
    {
        return "amazon_listing_preview:{$listing->id}:{$hash}";
    }

    private function assertAccess(Request $request, Listing $listing): void
    {
        abort_unless($listing->organization_id === (int) $request->attributes->get('organization_id'), 404);
    }
}
