<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'returned'];

    protected $fillable = [
        'organization_id', 'client_id', 'channel_account_id', 'platform',
        'external_order_id', 'status', 'order_date', 'fulfillment_type',
        'marketplace_id', 'items', 'units', 'subtotal', 'tax', 'shipping',
        'total', 'currency', 'customer_city', 'customer_state',
        'customer_pincode', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'items' => 'array',
            'metadata' => 'array',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class, 'channel_account_id');
    }
}
