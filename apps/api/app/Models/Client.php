<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['organization_id', 'name', 'slug', 'contact_name', 'contact_email', 'status', 'notes'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function channelAccounts(): HasMany
    {
        return $this->hasMany(ChannelAccount::class);
    }

    public function amazonAccounts(): HasMany
    {
        return $this->hasMany(AmazonAccount::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AgencyTask::class);
    }
}
