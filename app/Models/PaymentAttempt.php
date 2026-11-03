<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAttempt extends Model
{
    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const UNCERTAIN = 'uncertain';

    public const CANCELLED = 'cancelled';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'actor_snapshot' => 'array', 'diagnostics' => 'array',
            'verified_at' => 'datetime', 'confirmed_at' => 'datetime',
            'requested_at' => 'datetime', 'operation_expires_at' => 'datetime', 'notification_claimed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Financial writes belong to OrderPaymentService, including forceFill/save paths.
        static::saving(fn () => throw new LogicException('Use OrderPaymentService for payment writes.'));
        static::deleting(fn () => throw new LogicException('Payment evidence cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
