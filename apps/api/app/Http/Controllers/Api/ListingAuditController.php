<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingAudit;
use App\Services\ListingOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingAuditController extends Controller
{
    public function store(Request $request, Listing $listing, ListingOptimizer $optimizer): JsonResponse
    {
        abort_unless($listing->organization_id === $request->attributes->get('organization_id'), 404);
        $r = $optimizer->audit($listing);
        $audit = ListingAudit::create(['organization_id' => $listing->organization_id, 'client_id' => $listing->client_id, 'listing_id' => $listing->id, 'score' => $r['score'], 'breakdown' => $r['breakdown'], 'recommendations' => $r['recommendations'], 'audited_at' => now()]);

        return response()->json(['data' => ['audit' => $audit, 'keyword_coverage_percent' => $r['keyword_coverage_percent']]], 201);
    }
}
