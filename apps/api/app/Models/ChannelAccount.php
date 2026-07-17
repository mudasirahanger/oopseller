<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelAccount extends Model
{
    use HasFactory;

    protected $table = 'channel_accounts';

    protected $fillable = [
        'organization_id', 'client_id', 'platform', 'account_identifier', 'name', 'region',
        'refresh_token', 'credentials', 'status', 'authorized_at', 'token_last_refreshed_at',
        'last_synced_at', 'last_sync_error', 'metadata',
    ];

    protected $hidden = ['refresh_token', 'credentials'];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'token_last_refreshed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
            'credentials' => 'encrypted:array',
        ];
    }

    protected function refreshToken(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? decrypt($value) : null,
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function marketplaces(): BelongsToMany
    {
        return $this->belongsToMany(Marketplace::class, 'channel_account_marketplace', 'channel_account_id')
            ->withPivot(['enabled'])
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'channel_account_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'channel_account_id');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(ChannelSyncRun::class, 'channel_account_id');
    }
}
