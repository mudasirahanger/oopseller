<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\AgencyTask;
use App\Models\Client;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\ListingAudit;
use App\Models\MetricSnapshot;
use App\Models\Product;

class DashboardService
{
    public function summary(int $organizationId): array
    {
        $latestAudit = ListingAudit::query()->where('organization_id', $organizationId)->latest('audited_at')->value('score');
        $revenue = MetricSnapshot::query()->where('organization_id', $organizationId)->whereDate('metric_date', '>=', now()->subDays(30))->sum('revenue');
        $openStatuses = [TaskStatus::BACKLOG->value, TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value, TaskStatus::REVIEW->value, TaskStatus::CLIENT_APPROVAL->value];

        return [
            'stats' => [
                ['label' => 'Active clients', 'value' => Client::where('organization_id', $organizationId)->where('status', 'active')->count(), 'change' => null],
                ['label' => 'Managed ASINs', 'value' => Product::where('organization_id', $organizationId)->where('status', 'active')->count(), 'change' => null],
                ['label' => 'Tracked keywords', 'value' => Keyword::where('organization_id', $organizationId)->where('status', 'active')->count(), 'change' => null],
                ['label' => 'Open tasks', 'value' => AgencyTask::where('organization_id', $organizationId)->whereIn('status', $openStatuses)->count(), 'change' => null],
                ['label' => '30-day revenue', 'value' => round((float) $revenue, 2), 'change' => null, 'format' => 'currency'],
                ['label' => 'Latest listing score', 'value' => $latestAudit ?? 0, 'change' => null, 'format' => 'score'],
            ],
            'clients' => Client::query()->where('organization_id', $organizationId)->withCount(['products', 'tasks' => fn ($q) => $q->whereNotIn('status', [TaskStatus::DONE->value, TaskStatus::CANCELLED->value])])->latest()->limit(8)->get(['id', 'name', 'status']),
            'tasks' => AgencyTask::query()->where('organization_id', $organizationId)->with(['client:id,name', 'assignee:id,name'])->whereIn('status', $openStatuses)->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")->limit(8)->get(),
            'rankMovements' => $this->rankMovements($organizationId),
            'opportunities' => ListingAudit::query()->where('organization_id', $organizationId)->with('listing.product')->where('score', '<', 80)->latest('audited_at')->limit(6)->get(),
        ];
    }

    private function rankMovements(int $organizationId): array
    {
        $rankings = KeywordRanking::query()->where('organization_id', $organizationId)->with(['keyword.project.product'])->orderByDesc('observed_at')->limit(200)->get()->groupBy('keyword_id');

        return $rankings->map(function ($rows) {
            $current = $rows->get(0);
            $previous = $rows->get(1);

            return ['keyword' => $current?->keyword?->phrase, 'asin' => $current?->keyword?->project?->product?->asin, 'position' => $current?->organic_position, 'change' => ($current?->organic_position && $previous?->organic_position) ? $previous->organic_position - $current->organic_position : null];
        })->filter(fn ($x) => $x['keyword'])->take(8)->values()->all();
    }
}
