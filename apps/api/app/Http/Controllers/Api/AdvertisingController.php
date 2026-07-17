<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvertisingCampaign;
use App\Models\AdvertisingMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = (int) $request->attributes->get('organization_id');
        $from = $request->date('from') ?? now()->subDays(30);
        $to = $request->date('to') ?? now();

        $summary = AdvertisingMetric::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('metric_date', [$from, $to])
            ->selectRaw('SUM(impressions) impressions, SUM(clicks) clicks, SUM(spend) spend, SUM(sales) sales, SUM(orders) orders')
            ->first();

        $spend = (float) ($summary?->spend ?? 0);
        $sales = (float) ($summary?->sales ?? 0);

        $campaigns = AdvertisingCampaign::query()
            ->where('organization_id', $organizationId)
            ->withSum(['metrics as spend' => fn ($query) => $query->whereBetween('metric_date', [$from, $to])], 'spend')
            ->withSum(['metrics as sales' => fn ($query) => $query->whereBetween('metric_date', [$from, $to])], 'sales')
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => [
                'summary' => [
                    'impressions' => (int) ($summary?->impressions ?? 0),
                    'clicks' => (int) ($summary?->clicks ?? 0),
                    'spend' => $spend,
                    'sales' => $sales,
                    'orders' => (int) ($summary?->orders ?? 0),
                    'acos' => $sales > 0 ? round(($spend / $sales) * 100, 2) : 0,
                    'roas' => $spend > 0 ? round($sales / $spend, 2) : 0,
                ],
                'campaigns' => $campaigns,
            ],
        ]);
    }
}
