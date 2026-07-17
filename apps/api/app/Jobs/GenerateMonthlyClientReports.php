<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ClientReport;
use App\Models\MetricSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlyClientReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $clientId = null)
    {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $periodEnd = now()->startOfMonth()->subDay();
        $periodStart = $periodEnd->copy()->startOfMonth();
        Client::query()->when($this->clientId, fn ($q) => $q->whereKey($this->clientId))->where('status', 'active')->each(function (Client $client) use ($periodStart, $periodEnd) {
            $metrics = MetricSnapshot::where('client_id', $client->id)->whereBetween('metric_date', [$periodStart, $periodEnd])->selectRaw('SUM(revenue) revenue, SUM(orders) orders, SUM(units) units, SUM(ad_spend) ad_spend, SUM(ad_sales) ad_sales')->first();
            ClientReport::updateOrCreate(['client_id' => $client->id, 'type' => 'monthly_growth', 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()], ['organization_id' => $client->organization_id, 'status' => 'ready', 'summary' => ['headline' => 'Monthly Amazon growth report', 'generated_by' => 'OopSeller'], 'metrics' => $metrics?->toArray() ?? [], 'generated_at' => now()]);
        });
    }
}
