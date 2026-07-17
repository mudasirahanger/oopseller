<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\ListingAudit;
use App\Services\ListingOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunListingAudit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $listingId)
    {
        $this->onQueue('default');
    }

    public function handle(ListingOptimizer $optimizer): void
    {
        $listing = Listing::findOrFail($this->listingId);
        $result = $optimizer->audit($listing);
        ListingAudit::create(['organization_id' => $listing->organization_id, 'client_id' => $listing->client_id, 'listing_id' => $listing->id, 'score' => $result['score'], 'breakdown' => $result['breakdown'], 'recommendations' => $result['recommendations'], 'audited_at' => now()]);
    }
}
