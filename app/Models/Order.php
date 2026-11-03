<?php

namespace App\Models;

use App\Services\OrderPaymentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use LogicException;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'notes',
        'admin_note',
        'status',
        'subtotal',
        'total',
        'payment_method',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            if (! $order->exists) {
                if (($order->payment_status ?? self::PAYMENT_STATUS_UNPAID) !== self::PAYMENT_STATUS_UNPAID
                    || ($order->status ?? self::STATUS_PENDING) !== self::STATUS_PENDING) {
                    throw new LogicException('New orders must start pending and unpaid.');
                }
            } elseif ($order->isDirty(['status', 'payment_status', 'payment_method', 'total', 'subtotal', 'user_id'])) {
                throw new LogicException('Use controlled order transitions; financial snapshots are immutable.');
            }
        });
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function paymentProvenanceLabel(): string
    {
        $attempt = $this->paymentAttempts()->where('settlement_order_id', $this->id)->first();
        $source = $attempt?->source;

        return match ($source) {
            'manual_confirmed' => 'تأیید دستی مدیر',
            'gateway_verified' => $attempt->environment === 'sandbox' ? 'تأیید آزمایشی درگاه (Sandbox)' : 'تأیید درگاه',
            default => $this->isPaid() || $this->status === self::STATUS_PAID
                ? 'پرداخت قدیمی؛ منشأ تأیید نامشخص' : 'بدون تأیید پرداخت',
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'در انتظار',
            self::STATUS_PAID => 'پرداخت‌شده',
            self::STATUS_CANCELLED => 'لغوشده',
            self::STATUS_COMPLETED => 'تکمیل‌شده',
            default => $this->status,
        };
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_STATUS_UNPAID => 'پرداخت‌نشده',
            self::PAYMENT_STATUS_PAID => 'پرداخت‌شده',
            self::PAYMENT_STATUS_FAILED => 'ناموفق',
            default => $this->payment_status,
        };
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED], true);
    }

    public function markPaid(?User $actor = null, string $reason = '', ?string $reference = null): bool
    {
        app(OrderPaymentService::class)->confirmManual($this, $actor ?? Auth::guard('web')->user(), $reason, $reference);
        $this->refresh();

        return true;
    }

    public function markCompleted(): bool
    {
        $result = app(OrderPaymentService::class)->complete($this, Auth::guard('web')->user());
        $this->refresh();

        return $result;
    }

    public function cancel(): bool
    {
        $result = app(OrderPaymentService::class)->cancel($this, Auth::guard('web')->user());
        $this->refresh();

        return $result;
    }
}
