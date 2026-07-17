<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'keyword_project_id', 'phrase', 'type', 'priority', 'search_volume', 'relevance_score', 'listing_coverage', 'status'];

    protected function casts(): array
    {
        return ['listing_coverage' => 'boolean', 'relevance_score' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KeywordProject::class, 'keyword_project_id');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(KeywordRanking::class);
    }
}
