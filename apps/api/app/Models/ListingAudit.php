<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingAudit extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'listing_id', 'score', 'breakdown', 'recommendations', 'audited_at'];

    protected function casts(): array
    {
        return ['breakdown' => 'array', 'recommendations' => 'array', 'audited_at' => 'datetime'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
