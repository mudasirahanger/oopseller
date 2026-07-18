<?php

use App\Jobs\Channels\SyncChannelOrders;
use App\Jobs\CheckKeywordRanks;
use App\Jobs\GenerateMonthlyClientReports;
use App\Models\ChannelAccount;
use App\Models\ChannelSyncRun;
use App\Models\KeywordProject;
use App\Services\Channels\ChannelManager;
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

Schedule::call(function (): void {
    $manager = app(ChannelManager::class);

    ChannelAccount::query()
        ->where('status', 'active')
        ->get()
        ->filter(fn (ChannelAccount $account) => $manager->has($account->platform))
        ->each(function (ChannelAccount $account): void {
            $pending = ChannelSyncRun::query()
                ->where('channel_account_id', $account->id)
                ->where('type', 'orders')
                ->whereIn('status', ['queued', 'running'])
                ->where('created_at', '>=', now()->subMinutes(30))
                ->exists();

            if ($pending) {
                return;
            }

            $run = ChannelSyncRun::create([
                'organization_id' => $account->organization_id,
                'client_id' => $account->client_id,
                'platform' => $account->platform,
                'channel_account_id' => $account->id,
                'marketplace_id' => $account->platform,
                'type' => 'orders',
                'status' => 'queued',
            ]);
            SyncChannelOrders::dispatch($account->id, $run->id);
        });
})
    ->name('orders:sync-active-channel-accounts')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->name('sanctum:prune-expired-tokens')
    ->daily();

if (config('queue.default') === 'redis') {
    Schedule::command('horizon:snapshot')
        ->name('horizon:snapshot')
        ->everyFiveMinutes();
}
