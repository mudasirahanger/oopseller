<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'client_id', 'platform', 'channel_account_id', 'product_id',
        'marketplace_id', 'seller_sku', 'title', 'bullet_points', 'description',
        'backend_terms', 'attributes', 'amazon_issues', 'offers',
        'fulfillment_availability', 'relationships', 'product_types', 'raw_payload',
        'image_count', 'a_plus_status', 'status', 'last_imported_at',
        'last_sync_error', 'last_published_at',
    ];

    protected function casts(): array
    {
        return [
            'bullet_points' => 'array',
            'backend_terms' => 'array',
            'attributes' => 'array',
            'amazon_issues' => 'array',
            'offers' => 'array',
            'fulfillment_availability' => 'array',
            'relationships' => 'array',
            'product_types' => 'array',
            'raw_payload' => 'array',
            'last_imported_at' => 'datetime',
            'last_published_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class, 'channel_account_id');
    }

    public function amazonAccount(): BelongsTo
    {
        return $this->belongsTo(AmazonAccount::class, 'channel_account_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ListingVersion::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ListingAudit::class);
    }
}
