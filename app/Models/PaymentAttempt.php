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

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'در انتظار',
            self::SUCCEEDED => 'موفق',
            self::FAILED => 'ناموفق',
            self::UNCERTAIN => 'نامشخص',
            self::CANCELLED => 'لغوشده',
            default => 'وضعیت ثبت‌شده',
        };
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            'manual' => 'پرداخت دستی',
            'zarinpal' => 'زرین‌پال',
            default => 'روش ثبت‌شده',
        };
    }

    public function environmentLabel(): string
    {
        return match ($this->environment) {
            'sandbox' => 'آزمایشی',
            'production' => 'عملیاتی',
            default => 'بدون محیط درگاه',
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'manual_confirmed' => 'تأیید دستی مدیر',
            'gateway_verified' => 'تأیید درگاه',
            default => 'ثبت‌نشده',
        };
    }

    public function verificationOutcomeLabel(): string
    {
        return match ($this->verification_outcome) {
            'manual_confirmed' => 'تأیید دستی مدیر',
            'gateway_verified' => 'تأیید درگاه',
            'verified' => 'پرداخت تأیید شد',
            default => 'ثبت‌نشده',
        };
    }

    public function diagnosticLabel(): string
    {
        $code = data_get($this->diagnostics, 'code');

        return match ($code) {
            'initiated' => 'درخواست پرداخت آماده شد',
            'initiation_rejected', 'verification_rejected' => 'درخواست پرداخت تأیید نشد',
            'initiation_unknown', 'verification_unknown', 'transport_unknown', 'inquiry_unknown' => 'نتیجه نیازمند بررسی است',
            'provider_in_bank' => 'پرداخت در انتظار پاسخ درگاه است',
            'provider_paid', 'verified' => 'پرداخت تأیید شد',
            'provider_failed' => 'پرداخت ناموفق بود',
            'provider_reversed' => 'پرداخت برگشت خورده است',
            'cancelled_before_send' => 'درخواست پیش از ارسال لغو شد',
            'initiation_unavailable' => 'شروع پرداخت در دسترس نبود',
            'persistence_conflict' => 'نیازمند بررسی سوابق ثبت‌شده است',
            default => $code ? 'نتیجهٔ ثبت‌شده' : 'ثبت‌نشده',
        };
    }

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
