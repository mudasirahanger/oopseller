<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMonthlyClientReports;
use App\Models\Client;
use App\Models\ClientReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            ClientReport::query()
                ->where('organization_id', $request->attributes->get('organization_id'))
                ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->integer('client_id')))
                ->with('client:id,name')
                ->latest('period_end')
                ->paginate(min(100, max(1, $request->integer('per_page', 30)))),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['client_id' => ['required', 'integer', 'exists:clients,id']]);
        $organizationId = (int) $request->attributes->get('organization_id');
        $client = Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        abort_unless($client->status === 'active', 422, 'Only active clients can generate a monthly report.');

        GenerateMonthlyClientReports::dispatch($client->id);

        return response()->json(['message' => 'Report generation queued.'], 202);
    }
}
