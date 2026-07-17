<?php

use App\Jobs\CheckKeywordRanks;
use App\Jobs\GenerateMonthlyClientReports;
use App\Models\KeywordProject;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    if (config('services.rank_provider.driver') === 'null') {
        return;
    }

    KeywordProject::query()
        ->where('status', 'active')
        ->pluck('id')
        ->each(fn ($id) => CheckKeywordRanks::dispatch($id));
})
    ->name('keywords:check-active-projects')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::job(new GenerateMonthlyClientReports)
    ->name('reports:generate-monthly-client-reports')
    ->monthlyOn(2, '04:00')
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->name('sanctum:prune-expired-tokens')
    ->daily();

if (config('queue.default') === 'redis') {
    Schedule::command('horizon:snapshot')
        ->name('horizon:snapshot')
        ->everyFiveMinutes();
}
