<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class ClientProject extends Model
{
    use HasFactory;

    protected $attributes = ['delivery_mode' => 'time'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const SCHEDULE_RECURRING = 'recurring';

    public const SCHEDULE_FIXED_PERIOD = 'fixed_period';

    protected $fillable = [
        'customer_id',
        'title',
        'description',
        'type',
        'status',
        'schedule_mode',
        'delivery_mode',
        'progress',
        'monthly_hour_limit_minutes',
        'cycle_anchor_day',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'monthly_hour_limit_minutes' => 'integer',
            'cycle_anchor_day' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            if (! in_array($project->delivery_mode, ['time', 'tasks', 'hybrid'], true)) {
                throw ValidationException::withMessages(['delivery_mode' => 'نوع تحویل معتبر نیست.']);
            }
            if ($project->exists && $project->isDirty('delivery_mode')) {
                throw ValidationException::withMessages(['delivery_mode' => 'تغییر نوع تحویل پروژه موجود پشتیبانی نمی‌شود؛ پروژه جدید بسازید.']);
            }
        });
    }

    public function planVersions(): HasMany
    {
        return $this->hasMany(ClientProjectPlanVersion::class);
    }

    public function currentPlan(): HasOne
    {
        return $this->hasOne(ClientProjectPlanVersion::class)->ofMany(
            ['effective_from' => 'max', 'version' => 'max'],
            fn (Builder $query): Builder => $query->whereDate('effective_from', '<=', today()),
        );
    }

    public function supportsOperationalCycles(): bool
    {
        return $this->delivery_mode === 'time' || $this->planVersions()->exists();
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(ClientProjectWorkItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClientProjectActivity::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(ClientProjectCycle::class);
    }

    public function currentCycle(): HasOne
    {
        return $this->hasOne(ClientProjectCycle::class)
            ->ofMany(
                ['starts_at' => 'min'],
                fn (Builder $query): Builder => $query->containingDate(CarbonImmutable::today()),
            );
    }

    public function scopeForCustomer(Builder $query, Customer|int $customer): Builder
    {
        return $query->where('customer_id', $customer instanceof Customer ? $customer->getKey() : $customer);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRecurring(): bool
    {
        return $this->schedule_mode !== self::SCHEDULE_FIXED_PERIOD;
    }

    public function isFixedPeriod(): bool
    {
        return $this->schedule_mode === self::SCHEDULE_FIXED_PERIOD;
    }
}
