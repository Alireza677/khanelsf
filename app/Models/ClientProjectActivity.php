<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ClientProjectActivity extends Model
{
    use HasFactory;

    public const VISIBILITY_CLIENT = 'client';

    public const VISIBILITY_INTERNAL = 'internal';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    public const MAX_DURATION_MINUTES = 1440;

    protected $fillable = [
        'client_project_id', 'service_id', 'service_name_snapshot', 'service_unit_snapshot',
        'service_unit_label_snapshot', 'pricing_mode_snapshot', 'currency_snapshot',
        'unit_price_snapshot', 'quantity', 'total_amount', 'performed_by', 'activity_date', 'started_at', 'ended_at',
        'duration_minutes', 'title', 'description', 'internal_notes', 'visibility', 'status',
    ];

    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'unit_price_snapshot' => 'decimal:4',
            'quantity' => 'decimal:4',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $activity): void {
            $errors = [];
            $duration = (int) $activity->duration_minutes;

            if (! in_array($activity->visibility, [self::VISIBILITY_CLIENT, self::VISIBILITY_INTERNAL], true)) {
                $errors['visibility'] = 'Visibility is invalid.';
            }

            if (! in_array($activity->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_CANCELLED], true)) {
                $errors['status'] = 'Status is invalid.';
            }

            if ($duration < ($activity->status === self::STATUS_CANCELLED ? 0 : 1) || $duration > self::MAX_DURATION_MINUTES) {
                $errors['duration_minutes'] = 'Duration must be within the allowed range.';
            }

            if (($activity->started_at === null) !== ($activity->ended_at === null)) {
                $errors['started_at'] = 'Start and end must be supplied together.';
            } elseif ($activity->started_at && $activity->ended_at) {
                $seconds = (int) round(CarbonImmutable::parse($activity->started_at)->diffInSeconds(CarbonImmutable::parse($activity->ended_at), false));

                if ($seconds <= 0) {
                    $errors['ended_at'] = 'End time must be after start time.';
                } elseif ($seconds !== $duration * 60) {
                    $errors['duration_minutes'] = 'Duration must exactly match the supplied timestamps.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeForProject(Builder $query, ClientProject|int $project): Builder
    {
        return $query->where('client_project_id', $project instanceof ClientProject ? $project->getKey() : $project);
    }

    public function scopeForCustomer(Builder $query, Customer|int $customer): Builder
    {
        $customerId = $customer instanceof Customer ? $customer->getKey() : $customer;

        return $query->whereHas('project', fn (Builder $query): Builder => $query->where('customer_id', $customerId));
    }

    public function scopePublishedForClient(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->where('visibility', self::VISIBILITY_CLIENT);
    }

    public function scopeInMonth(Builder $query, CarbonImmutable $month): Builder
    {
        return $query->whereBetween('activity_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()]);
    }
}
