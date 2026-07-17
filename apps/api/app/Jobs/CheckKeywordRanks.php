<?php

namespace App\Jobs;

use App\Models\KeywordProject;
use App\Models\KeywordRanking;
use App\Services\Rankings\Contracts\RankProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckKeywordRanks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $projectId)
    {
        $this->onQueue('rankings');
    }

    public function handle(RankProvider $provider): void
    {
        if (config('services.rank_provider.driver') === 'null') {
            return;
        }
        $project = KeywordProject::with(['product', 'keywords' => fn ($q) => $q->where('status', 'active')])->findOrFail($this->projectId);
        foreach ($project->keywords as $keyword) {
            $r = $provider->lookup($project->product, $keyword, $project->marketplace_id);
            KeywordRanking::create(['organization_id' => $project->organization_id, 'client_id' => $project->client_id, 'keyword_id' => $keyword->id, 'product_id' => $project->product_id, 'marketplace_id' => $project->marketplace_id, 'organic_position' => $r->organicPosition, 'sponsored_position' => $r->sponsoredPosition, 'page_number' => $r->pageNumber, 'result_count' => $r->resultCount, 'provider' => $r->provider, 'confidence_score' => $r->confidence, 'observed_at' => now()]);
        }
    }
}
