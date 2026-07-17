<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'name', 'metric', 'operator', 'threshold', 'scope', 'channels', 'cooldown_minutes', 'enabled', 'last_triggered_at'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return ['scope' => 'array', 'channels' => 'array', 'enabled' => 'boolean', 'last_triggered_at' => 'datetime', 'threshold' => 'decimal:4'];
    }
}
