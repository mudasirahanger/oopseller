<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSyncRun extends Model
{
    protected $table = 'channel_sync_runs';

    protected $fillable = [
        'organization_id', 'client_id', 'platform', 'channel_account_id', 'marketplace_id',
        'type', 'status', 'processed', 'failed', 'error', 'started_at',
        'finished_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class, 'channel_account_id');
    }
}
