<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const REVENUE_STATUSES = ['confirmed', 'shipped', 'delivered'];

    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request)
            ->with(['client:id,name', 'channelAccount:id,name,platform'])
            ->latest('order_date');

        return response()->json($query->paginate(min(100, max(1, $request->integer('per_page', 30)))));
    }

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->window($request);
        $sales = $this->baseQuery($request)
            ->whereBetween('order_date', [$from, $to])
            ->whereIn('status', self::REVENUE_STATUSES);

        $totals = (clone $sales)
            ->selectRaw('COALESCE(SUM(total),0) as revenue, COUNT(*) as orders, COALESCE(SUM(units),0) as units')
            ->first();

        $byPlatform = (clone $sales)
            ->selectRaw('platform, COALESCE(SUM(total),0) as revenue, COUNT(*) as orders, COALESCE(SUM(units),0) as units')
            ->groupBy('platform')
            ->orderByDesc('revenue')
            ->get();

        $byDay = (clone $sales)
            ->selectRaw('DATE(order_date) as date, COALESCE(SUM(total),0) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topProducts = collect();
        (clone $sales)->select(['items'])->limit(2000)->get()
            ->flatMap(fn (Order $order) => (array) $order->items)
            ->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null))
            ->groupBy(fn (array $item) => $item['sku'] ?? $item['external_id'] ?? $item['name'])
            ->map(fn ($items, $key) => [
                'key' => (string) $key,
                'name' => (string) ($items->first()['name'] ?? $key),
                'units' => (int) $items->sum('quantity'),
                'revenue' => round((float) $items->sum('total'), 2),
            ])
            ->sortByDesc('revenue')
            ->take(10)
            ->values()
            ->each(fn (array $row) => $topProducts->push($row));

        $cancelled = $this->baseQuery($request)
            ->whereBetween('order_date', [$from, $to])
            ->whereIn('status', ['cancelled', 'returned'])
            ->count();

        $revenue = (float) ($totals->revenue ?? 0);
        $orders = (int) ($totals->orders ?? 0);

        return response()->json(['data' => [
            'window' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'revenue' => round($revenue, 2),
                'orders' => $orders,
                'units' => (int) ($totals->units ?? 0),
                'average_order_value' => $orders > 0 ? round($revenue / $orders, 2) : 0,
                'cancelled_or_returned' => $cancelled,
            ],
            'by_platform' => $byPlatform,
            'by_day' => $byDay,
            'top_products' => $topProducts,
        ]]);
    }

    private function baseQuery(Request $request): Builder
    {
        $query = Order::query()->where('organization_id', (int) $request->attributes->get('organization_id'));

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }
        if ($request->filled('platform')) {
            $query->where('platform', (string) $request->input('platform'));
        }
        if ($request->filled('status')) {
            $request->validate(['status' => [Rule::in(Order::STATUSES)]]);
            $query->where('status', (string) $request->input('status'));
        }

        return $query;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function window(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $to = $request->filled('to') ? Carbon::parse((string) $request->input('to'))->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse((string) $request->input('from'))->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return [$from, $to];
    }
}
