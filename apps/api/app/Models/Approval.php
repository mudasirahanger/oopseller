<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $fillable = ['organization_id', 'client_id', 'approvable_type', 'approvable_id', 'requested_by', 'decided_by', 'status', 'note', 'requested_at', 'decided_at'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function approvable()
    {
        return $this->morphTo();
    }
}
