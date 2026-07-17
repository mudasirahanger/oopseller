<?php

namespace App\Jobs\Amazon;

use App\Models\AmazonAccount;
use App\Models\ChannelSyncRun;
use App\Services\Amazon\AmazonCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAmazonListings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 900;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly int $accountId,
        public readonly string $marketplaceId,
        public readonly int $syncRunId,
    ) {
        $this->onQueue('amazon');
    }

    public function handle(AmazonCatalogSyncService $service): void
    {
        $service->syncListings(
            AmazonAccount::findOrFail($this->accountId),
            $this->marketplaceId,
            ChannelSyncRun::findOrFail($this->syncRunId),
        );
    }
}
