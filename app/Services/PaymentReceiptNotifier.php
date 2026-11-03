<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class PaymentReceiptNotifier
{
    public function send(int $attemptId): void
    {
        $attempt = PaymentAttempt::query()->with('order')->findOrFail($attemptId);
        if ($attempt->source !== 'gateway_verified' || $attempt->status !== PaymentAttempt::SUCCEEDED
            || ! $attempt->order->isPaid() || ! filled($attempt->order->customer_email)) {
            return;
        }
        // At-most-once dispatch claim. A crash/mail failure can lose mail, never payment.
        $claimed = DB::table('payment_attempts')->where('id', $attempt->id)->whereNull('notification_claimed_at')
            ->update(['notification_claimed_at' => now()]);
        if (! $claimed) {
            return;
        }
        $body = implode(PHP_EOL, [
            'پرداخت سفارش '.$attempt->order->order_number.' تأیید شد.',
            $attempt->environment === 'sandbox' ? 'محیط آزمایشی Sandbox؛ وجه واقعی دریافت نشده است.' : 'محیط واقعی درگاه',
            'مبلغ: '.app(ProductPricingService::class)->formatAmount($attempt->amount).' تومان',
            'مرجع پرداخت: '.$attempt->provider_reference,
            'تأیید پرداخت به معنی تکمیل یا تحویل سفارش نیست.',
        ]);
        rescue(fn () => Mail::raw($body, fn ($message) => $message
            ->to($attempt->order->customer_email)->subject('تأیید پرداخت سفارش '.$attempt->order->order_number)), report: false);
    }
}
