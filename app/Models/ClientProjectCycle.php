<?php

namespace App\Models;

use App\Enums\ClientProjectCycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClientProjectCycle extends Model
{
    protected $fillable = ['client_project_id', 'starts_at', 'ends_at', 'allocated_minutes', 'status', 'completed_at', 'invoiced_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'allocated_minutes' => 'integer', 'status' => ClientProjectCycleStatus::class, 'completed_at' => 'datetime', 'invoiced_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClientProjectActivity::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'client_project_cycle_id')->where('status', '!=', 'cancelled')->latestOfMany();
    }
}
