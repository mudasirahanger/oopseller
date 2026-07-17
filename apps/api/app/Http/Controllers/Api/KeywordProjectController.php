<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordProject;
use App\Models\Marketplace;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeywordProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            KeywordProject::query()
                ->where('organization_id', $request->attributes->get('organization_id'))
                ->with([
                    'product:id,asin,name',
                    'keywords' => fn ($query) => $query->with([
                        'rankings' => fn ($rankings) => $rankings->latest('observed_at')->limit(2),
                    ]),
                ])
                ->latest()
                ->paginate(min(100, max(1, $request->integer('per_page', 20)))),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'marketplace_id' => ['required', 'string', 'max:20', 'exists:marketplaces,amazon_marketplace_id'],
            'name' => ['required', 'string', 'max:150'],
            'language' => ['nullable', 'string', 'max:10'],
            'keywords' => ['required', 'array', 'min:1', 'max:250'],
            'keywords.*' => ['required', 'string', 'max:250'],
        ]);

        $organizationId = (int) $request->attributes->get('organization_id');
        $product = Product::where('organization_id', $organizationId)->findOrFail($data['product_id']);
        Marketplace::where('amazon_marketplace_id', $data['marketplace_id'])->firstOrFail();
        $phrases = collect($data['keywords'])->map(fn (string $phrase) => trim($phrase))->filter()->unique()->values();
        abort_if($phrases->isEmpty(), 422, 'At least one non-empty keyword is required.');

        $project = DB::transaction(function () use ($data, $organizationId, $product, $phrases): KeywordProject {
            $project = KeywordProject::create([
                'organization_id' => $organizationId,
                'client_id' => $product->client_id,
                'product_id' => $product->id,
                'marketplace_id' => $data['marketplace_id'],
                'name' => $data['name'],
                'language' => $data['language'] ?? 'en',
                'status' => 'active',
            ]);

            foreach ($phrases as $phrase) {
                Keyword::create([
                    'organization_id' => $organizationId,
                    'client_id' => $product->client_id,
                    'keyword_project_id' => $project->id,
                    'phrase' => $phrase,
                    'type' => 'secondary',
                    'priority' => 'medium',
                    'status' => 'active',
                ]);
            }

            return $project;
        });

        return response()->json(['data' => $project->load('product:id,asin,name', 'keywords')], 201);
    }

    public function addKeywords(Request $request, KeywordProject $keywordProject): JsonResponse
    {
        abort_unless($keywordProject->organization_id === (int) $request->attributes->get('organization_id'), 404);
        $data = $request->validate([
            'keywords' => ['required', 'array', 'min:1', 'max:250'],
            'keywords.*.phrase' => ['required', 'string', 'max:250'],
            'keywords.*.type' => ['nullable', 'in:primary,secondary,long_tail,ppc,competitor,branded,negative'],
            'keywords.*.priority' => ['nullable', 'in:low,medium,high'],
        ]);

        foreach ($data['keywords'] as $item) {
            $phrase = trim($item['phrase']);
            if ($phrase === '') {
                continue;
            }
            Keyword::updateOrCreate(
                ['keyword_project_id' => $keywordProject->id, 'phrase' => $phrase],
                [
                    'organization_id' => $keywordProject->organization_id,
                    'client_id' => $keywordProject->client_id,
                    'type' => $item['type'] ?? 'secondary',
                    'priority' => $item['priority'] ?? 'medium',
                    'status' => 'active',
                ],
            );
        }

        return response()->json(['data' => $keywordProject->load('keywords')]);
    }
}
