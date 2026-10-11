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
    protected $attributes = ['delivery_mode' => 'time'];

    protected $fillable = ['client_project_id', 'starts_at', 'ends_at', 'allocated_minutes', 'status', 'completed_at', 'invoiced_at', 'client_project_plan_version_id', 'delivery_mode', 'delivery_status', 'agreement_snapshot'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'allocated_minutes' => 'integer', 'status' => ClientProjectCycleStatus::class, 'completed_at' => 'datetime', 'invoiced_at' => 'datetime', 'agreement_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $cycle): void {
            if ($cycle->hasDeliveryHistory() && $cycle->isDirty(['client_project_id', 'starts_at', 'ends_at', 'allocated_minutes', 'client_project_plan_version_id', 'delivery_mode', 'agreement_snapshot'])) {
                throw new \LogicException('Delivery cycle commitments and boundaries are immutable.');
            }
        });
        static::deleting(function (self $cycle): void {
            if ($cycle->hasDeliveryHistory()) {
                throw new \LogicException('Delivery cycles are retained for history.');
            }
        });
    }

    public function hasDeliveryHistory(): bool
    {
        return $this->agreement_snapshot !== null || self::query()->whereKey($this)->whereNotNull('agreement_snapshot')->exists() || $this->commitments()->exists() || $this->workItems()->exists();
    }

    public function hasHourlyAllocation(): bool
    {
        return $this->delivery_mode === 'time' || ($this->delivery_mode === 'hybrid' && $this->allocated_minutes > 0);
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(ClientProjectCycleCommitment::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(ClientProjectWorkItem::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ClientCycleReport::class)->orderByDesc('version');
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
