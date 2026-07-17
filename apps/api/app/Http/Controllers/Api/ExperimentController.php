<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\OptimizationExperiment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperimentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            OptimizationExperiment::query()
                ->where('organization_id', $request->attributes->get('organization_id'))
                ->with(['product:id,asin,name', 'listing:id,marketplace_id,seller_sku'])
                ->latest()
                ->paginate(min(100, max(1, $request->integer('per_page', 30)))),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'name' => ['required', 'string', 'max:200'],
            'hypothesis' => ['nullable', 'array'],
            'changes' => ['nullable', 'array'],
            'baseline_start' => ['nullable', 'date'],
            'baseline_end' => ['nullable', 'date', 'after_or_equal:baseline_start'],
            'experiment_start' => ['nullable', 'date'],
            'experiment_end' => ['nullable', 'date', 'after_or_equal:experiment_start'],
        ]);

        $organizationId = (int) $request->attributes->get('organization_id');
        $product = Product::where('organization_id', $organizationId)->findOrFail($data['product_id']);
        if (filled($data['listing_id'] ?? null)) {
            Listing::where('organization_id', $organizationId)->where('product_id', $product->id)->findOrFail($data['listing_id']);
        }

        $experiment = OptimizationExperiment::create([
            ...$data,
            'organization_id' => $organizationId,
            'client_id' => $product->client_id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $experiment->load(['product:id,asin,name', 'listing:id,marketplace_id,seller_sku'])], 201);
    }

    public function update(Request $request, OptimizationExperiment $experiment): JsonResponse
    {
        abort_unless($experiment->organization_id === (int) $request->attributes->get('organization_id'), 404);
        $experiment->update($request->validate([
            'status' => ['sometimes', 'in:draft,baseline,running,completed,cancelled'],
            'hypothesis' => ['sometimes', 'nullable', 'array'],
            'changes' => ['sometimes', 'nullable', 'array'],
            'baseline_metrics' => ['sometimes', 'nullable', 'array'],
            'result_metrics' => ['sometimes', 'nullable', 'array'],
            'conclusion' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]));

        return response()->json(['data' => $experiment->fresh(['product:id,asin,name', 'listing:id,marketplace_id,seller_sku'])]);
    }
}
