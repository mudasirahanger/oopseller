<?php

namespace App\Jobs\Channels;

use App\Models\ChannelAccount;
use App\Models\ChannelSyncRun;
use App\Services\Channels\ChannelCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncChannelListings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 900;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly int $accountId,
        public readonly int $syncRunId,
    ) {
        $this->onQueue('amazon');
    }

    public function handle(ChannelCatalogSyncService $service): void
    {
        $service->syncListings(
            ChannelAccount::findOrFail($this->accountId),
            ChannelSyncRun::findOrFail($this->syncRunId),
        );
    }
}
