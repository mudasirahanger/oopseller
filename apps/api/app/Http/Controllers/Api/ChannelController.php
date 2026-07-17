<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelAccount;
use App\Services\Channels\ChannelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
