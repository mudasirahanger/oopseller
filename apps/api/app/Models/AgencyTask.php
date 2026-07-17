<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyTask extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'product_id', 'listing_id', 'assigned_to', 'created_by', 'type', 'title', 'description', 'priority', 'status', 'due_at', 'completed_at', 'metadata'];

    protected function casts(): array
    {
        return ['type' => TaskType::class, 'status' => TaskStatus::class, 'due_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
