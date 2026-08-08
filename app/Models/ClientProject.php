<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientProject extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'title',
        'description',
        'type',
        'status',
        'progress',
        'monthly_hour_limit_minutes',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'monthly_hour_limit_minutes' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClientProjectActivity::class);
    }

    public function scopeForCustomer(Builder $query, Customer|int $customer): Builder
    {
        return $query->where('customer_id', $customer instanceof Customer ? $customer->getKey() : $customer);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
