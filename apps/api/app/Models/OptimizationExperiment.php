<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationExperiment extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'product_id', 'listing_id', 'name', 'status', 'baseline_start', 'baseline_end', 'experiment_start', 'experiment_end', 'hypothesis', 'changes', 'baseline_metrics', 'result_metrics', 'conclusion'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    protected function casts(): array
    {
        return ['baseline_start' => 'date', 'baseline_end' => 'date', 'experiment_start' => 'date', 'experiment_end' => 'date', 'hypothesis' => 'array', 'changes' => 'array', 'baseline_metrics' => 'array', 'result_metrics' => 'array'];
    }
}
