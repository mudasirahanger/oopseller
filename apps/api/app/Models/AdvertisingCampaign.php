<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvertisingCampaign extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'advertising_profile_id', 'campaign_id', 'name', 'ad_type', 'targeting_type', 'state', 'daily_budget'];

    protected function casts(): array
    {
        return ['daily_budget' => 'decimal:2'];
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(AdvertisingMetric::class);
    }
}
