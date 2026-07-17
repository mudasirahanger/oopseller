<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\Marketplace;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Competitor::query()
            ->where('organization_id', $request->attributes->get('organization_id'))
            ->with(['product:id,asin,name', 'latestSnapshot']);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        return response()->json($query->latest()->paginate(min(100, max(1, $request->integer('per_page', 30)))));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'marketplace_id' => ['required', 'string', 'max:20', 'exists:marketplaces,amazon_marketplace_id'],
            'asin' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $organizationId = (int) $request->attributes->get('organization_id');
        $product = Product::where('organization_id', $organizationId)->findOrFail($data['product_id']);
        Marketplace::where('amazon_marketplace_id', $data['marketplace_id'])->firstOrFail();
        abort_if(strtoupper($data['asin']) === strtoupper($product->asin), 422, 'A product cannot be its own competitor.');

        $competitor = Competitor::updateOrCreate(
            [
                'product_id' => $product->id,
                'marketplace_id' => $data['marketplace_id'],
                'asin' => strtoupper($data['asin']),
            ],
            [
                'organization_id' => $organizationId,
                'client_id' => $product->client_id,
                'name' => $data['name'] ?? null,
                'status' => 'active',
            ],
        );

        return response()->json(['data' => $competitor->load('product:id,asin,name', 'latestSnapshot')], 201);
    }

    public function update(Request $request, Competitor $competitor): JsonResponse
    {
        abort_unless($competitor->organization_id === (int) $request->attributes->get('organization_id'), 404);
        $competitor->update($request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,paused,archived'],
        ]));

        return response()->json(['data' => $competitor->fresh(['product:id,asin,name', 'latestSnapshot'])]);
    }
}
