<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitorSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['competitor_id', 'price', 'rating', 'review_count', 'category_rank', 'in_stock', 'featured_offer_seller', 'content_hashes', 'observed_at'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'rating' => 'decimal:2', 'in_stock' => 'boolean', 'content_hashes' => 'array', 'observed_at' => 'datetime'];
    }
}
