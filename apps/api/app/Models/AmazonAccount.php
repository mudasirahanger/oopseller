<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;

// Amazon-scoped view over the generic channel_accounts table. Existing Amazon
// code (SP-API services, OAuth flow) works with this class; other platforms
// use ChannelAccount with their own platform scope.
class AmazonAccount extends ChannelAccount
{
    protected static function booted(): void
    {
        static::addGlobalScope('platform', function (Builder $query): void {
            $query->where('channel_accounts.platform', Platform::Amazon->value);
        });

        static::creating(function (self $account): void {
            $account->platform = Platform::Amazon->value;
        });
    }
}
