<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReport extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'type', 'period_start', 'period_end', 'status', 'summary', 'metrics', 'file_path', 'generated_at'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'summary' => 'array', 'metrics' => 'array', 'generated_at' => 'datetime'];
    }
}
