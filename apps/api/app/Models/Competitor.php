<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Competitor extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'product_id', 'marketplace_id', 'asin', 'name', 'status', 'last_snapshot_at'];

    protected function casts(): array
    {
        return ['last_snapshot_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CompetitorSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(CompetitorSnapshot::class)->latestOfMany('observed_at');
    }
}
