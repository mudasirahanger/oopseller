<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordRanking extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'client_id', 'keyword_id', 'product_id', 'marketplace_id', 'organic_position', 'sponsored_position', 'page_number', 'result_count', 'provider', 'confidence_score', 'observed_at'];

    protected function casts(): array
    {
        return ['confidence_score' => 'decimal:2', 'observed_at' => 'datetime'];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
