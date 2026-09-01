<?php

namespace App\Models;

use App\Enums\ClientProjectCycleStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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

    public function scopeContainingDate(Builder $query, CarbonInterface|string $date): Builder
    {
        $date = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->toDateString()
            : CarbonImmutable::parse($date)->toDateString();

        return $query
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>', $date);
    }

    public function containsDate(CarbonInterface|string $date): bool
    {
        $date = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->startOfDay()
            : CarbonImmutable::parse($date)->startOfDay();

        return $this->starts_at->startOfDay()->lte($date)
            && $this->ends_at->startOfDay()->gt($date);
    }
}
