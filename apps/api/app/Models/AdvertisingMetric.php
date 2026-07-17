<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisingMetric extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'client_id', 'advertising_campaign_id', 'metric_date', 'impressions', 'clicks', 'spend', 'sales', 'orders', 'acos', 'roas', 'recorded_at'];

    protected function casts(): array
    {
        return ['metric_date' => 'date', 'spend' => 'decimal:2', 'sales' => 'decimal:2', 'acos' => 'decimal:4', 'roas' => 'decimal:4', 'recorded_at' => 'datetime'];
    }
}
