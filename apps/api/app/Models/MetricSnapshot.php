<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'client_id', 'product_id', 'marketplace_id', 'metric_date', 'revenue', 'orders', 'units', 'sessions', 'conversion_rate', 'ad_spend', 'ad_sales', 'acos', 'tacos', 'organic_sales', 'refund_rate', 'recorded_at'];

    protected function casts(): array
    {
        return ['metric_date' => 'date', 'recorded_at' => 'datetime', 'revenue' => 'decimal:2', 'conversion_rate' => 'decimal:4', 'ad_spend' => 'decimal:2', 'ad_sales' => 'decimal:2', 'acos' => 'decimal:4', 'tacos' => 'decimal:4', 'organic_sales' => 'decimal:2', 'refund_rate' => 'decimal:4'];
    }
}
