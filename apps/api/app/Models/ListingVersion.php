<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingVersion extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'listing_id', 'created_by', 'version', 'source', 'content', 'target_keywords', 'change_summary', 'published_at'];

    protected function casts(): array
    {
        return ['content' => 'array', 'target_keywords' => 'array', 'published_at' => 'datetime'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
