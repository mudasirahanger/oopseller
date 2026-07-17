<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvertisingProfile extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'channel_account_id', 'profile_id', 'marketplace_id', 'name', 'currency', 'status', 'last_synced_at'];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdvertisingCampaign::class);
    }
}
