<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmazonAccount;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Listing;
use App\Models\Product;
use App\Services\Amazon\AmazonCatalogSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->whereHas('client')
            ->where('organization_id', $request->attributes->get('organization_id'))
            ->with(['client:id,name', 'brand:id,name', 'channelAccount:id,name,platform,account_identifier,status', 'listings']);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('search')) {
            $search = trim(str_replace(['%', '_'], ' ', (string) $request->input('search')));
            $query->where(fn ($builder) => $builder
                ->where('asin', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        return response()->json($query->latest()->paginate(min(100, max(1, $request->integer('per_page', 30)))));
    }

    public function store(Request $request, AmazonCatalogSyncService $amazon): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'channel_account_id' => ['nullable', 'integer', 'exists:channel_accounts,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'asin' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'sku' => ['nullable', 'string', 'max:100'],
            'name' => ['required_unless:import_from_amazon,true', 'nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:120'],
            'marketplace_id' => ['nullable', 'string', 'exists:marketplaces,amazon_marketplace_id'],
            'import_from_amazon' => ['sometimes', 'boolean'],
        ]);

        $organizationId = (int) $request->attributes->get('organization_id');
        $client = Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        abort_if(Product::withTrashed()->where('client_id', $client->id)->where('asin', strtoupper($data['asin']))->exists(), 422, 'This ASIN already exists or is archived for the selected client.');

        if (filled($data['brand_id'] ?? null)) {
            Brand::where('organization_id', $organizationId)->where('client_id', $client->id)->findOrFail($data['brand_id']);
        }

        if (filled($data['channel_account_id'] ?? null)) {
            AmazonAccount::where('organization_id', $organizationId)->where('client_id', $client->id)->findOrFail($data['channel_account_id']);
        }

        if ($data['import_from_amazon'] ?? false) {
            abort_unless(filled($data['channel_account_id'] ?? null) && filled($data['marketplace_id'] ?? null), 422, 'Amazon account and marketplace are required for import.');
            $account = AmazonAccount::where('organization_id', $organizationId)
                ->where('client_id', $client->id)
                ->where('status', 'active')
                ->findOrFail($data['channel_account_id']);
            abort_unless($account->marketplaces()->where('amazon_marketplace_id', $data['marketplace_id'])->wherePivot('enabled', true)->exists(), 422, 'This marketplace is not enabled for the selected Amazon account.');

            $isSandbox = (bool) ($account->metadata['sandbox'] ?? config('services.amazon.sandbox'));
            if ($isSandbox) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'asin' => 'Cannot manually import products while in Sandbox mode. Please reconnect in Production mode to import real Amazon catalog data.'
                ]);
            }

            return response()->json([
                'data' => $amazon->importCatalogItem($account, $data['marketplace_id'], strtoupper($data['asin']), $data['sku'] ?? null),
            ], 201);
        }

        $product = DB::transaction(function () use ($data, $organizationId, $client): Product {
            $product = Product::create([
                'organization_id' => $organizationId,
                'client_id' => $client->id,
                'channel_account_id' => $data['channel_account_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'asin' => strtoupper($data['asin']),
                'external_id' => strtoupper($data['asin']),
                'sku' => $data['sku'] ?? null,
                'name' => $data['name'],
                'product_type' => $data['product_type'] ?? null,
                'status' => 'active',
                'source' => 'manual',
            ]);

            if (filled($data['marketplace_id'] ?? null)) {
                Listing::create([
                    'organization_id' => $organizationId,
                    'client_id' => $client->id,
                    'channel_account_id' => $data['channel_account_id'] ?? null,
                    'product_id' => $product->id,
                    'marketplace_id' => $data['marketplace_id'],
                    'seller_sku' => $data['sku'] ?? null,
                    'title' => $data['name'],
                    'status' => 'draft',
                ]);
            }

            return $product;
        });

        return response()->json(['data' => $product->load('listings')], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->assertAccess($request, $product);

        return response()->json(['data' => $product->load([
            'client', 'brand', 'channelAccount', 'listings.audits', 'keywordProjects.keywords.rankings',
        ])]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->assertAccess($request, $product);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'product_type' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(['active', 'paused', 'archived'])],
        ]);
        if (filled($data['brand_id'] ?? null)) {
            Brand::where('organization_id', $product->organization_id)->where('client_id', $product->client_id)->findOrFail($data['brand_id']);
        }
        $product->update($data);

        return response()->json(['data' => $product->fresh(['listings'])]);
    }

    public function refreshAmazon(Request $request, Product $product, AmazonCatalogSyncService $amazon): JsonResponse
    {
        $this->assertAccess($request, $product);
        $data = $request->validate([
            'marketplace_id' => ['required', 'string', 'exists:marketplaces,amazon_marketplace_id'],
            'seller_sku' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['data' => $amazon->refreshProduct($product, $data['marketplace_id'], $data['seller_sku'] ?? null)]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->assertAccess($request, $product);
        $this->requireOrganizationRole($request, 'owner', 'admin');
        $product->delete();

        return response()->json(status: 204);
    }

    private function assertAccess(Request $request, Product $product): void
    {
        abort_unless($product->organization_id === (int) $request->attributes->get('organization_id'), 404);
    }
}
