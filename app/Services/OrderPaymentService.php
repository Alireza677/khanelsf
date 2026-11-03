<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class OrderPaymentService
{
    public function __construct(private readonly ProductPricingService $pricing) {}

    public function authorizeAdministrator(?User $actor): User
    {
        // Re-read privileges: a stale authenticated model must not retain revoked access.
        $actor = $actor ? User::query()->find($actor->getKey()) : null;
        abort_unless($actor?->isAdmin() && $actor->isActive(), 403);

        return $actor;
    }

    public function confirmManual(Order $order, ?User $actor, string $reason, ?string $reference = null): PaymentAttempt
    {
        $actor = $this->authorizeAdministrator($actor);
        $reason = trim($reason);
        $reference = filled($reference) ? trim($reference) : null;
        if ($reason === '' || mb_strlen($reason) > 2000 || mb_strlen($reference ?? '') > 255) {
            $this->reject('دلیل تأیید پرداخت الزامی است؛ طول دلیل یا مرجع معتبر نیست.');
        }

        return DB::transaction(function () use ($order, $actor, $reason, $reference): PaymentAttempt {
            $order = $this->lockedOrder($order);
            $settled = $order->paymentAttempts()->where('settlement_order_id', $order->id)->first();
            if ($order->isPaid() && $settled?->source === 'manual_confirmed') {
                return $settled; // Preserve the first actor, reason and timestamp on replay.
            }
            $this->assertPayable($order);
            if ($order->payment_method !== 'manual') {
                $this->reject('این سفارش برای تأیید پرداخت دستی ثبت نشده است.');
            }
            if ($order->paymentAttempts()->whereNotNull('active_order_id')->exists()) {
                $this->reject('تلاش پرداخت باز یا نامشخص باید ابتدا تعیین تکلیف شود.');
            }
            $now = now();
            $id = DB::table('payment_attempts')->insertGetId([
                ...$this->snapshot($order),
                'initiation_key' => hash('sha256', 'manual-settlement:'.$order->id),
                'status' => PaymentAttempt::SUCCEEDED,
                'settlement_order_id' => $order->id,
                'source' => 'manual_confirmed',
                'verification_outcome' => 'manual_confirmed',
                'confirmed_by' => $actor->id,
                'actor_snapshot' => json_encode(['id' => $actor->id, 'name' => $actor->name], JSON_THROW_ON_ERROR),
                'confirmed_at' => $now,
                'manual_reference' => $reference,
                'confirmation_reason' => $reason,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'status' => $order->isPending() ? Order::STATUS_PAID : $order->status,
                'updated_at' => $now,
            ]);

            return PaymentAttempt::query()->findOrFail($id);
        }, 3);
    }

    /** Reserve an internal intent only. P2 never contacts a provider or creates an authority. */
    public function prepare(Order $order, string $intent): PaymentAttempt
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{16,128}$/D', $intent)) {
            $this->reject('شناسه تلاش پرداخت معتبر نیست.');
        }

        return DB::transaction(function () use ($order, $intent): PaymentAttempt {
            $order = $this->lockedOrder($order);
            $this->assertPayable($order);
            $key = hash('sha256', $intent);
            $existing = PaymentAttempt::query()->where('initiation_key', $key)->first();
            if ($existing) {
                if ($existing->order_id !== $order->id) {
                    $this->reject('شناسه تلاش قبلاً برای سفارش دیگری استفاده شده است.');
                }

                return $existing; // Failed/uncertain replays never implicitly reinitiate.
            }
            if ($order->paymentAttempts()->whereNotNull('active_order_id')->exists()) {
                $this->reject('سفارش یک تلاش باز یا نامشخص دارد؛ شروع مجدد مجاز نیست.');
            }
            $id = DB::table('payment_attempts')->insertGetId([
                ...$this->snapshot($order), 'initiation_key' => $key,
                'status' => PaymentAttempt::PENDING, 'active_order_id' => $order->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return PaymentAttempt::query()->findOrFail($id);
        }, 3);
    }

    /** For internal pre-network failures/uncertainty; never accept callback payloads here. */
    public function recordUnsettled(PaymentAttempt $attempt, string $status, string $code): PaymentAttempt
    {
        if (! in_array($status, [PaymentAttempt::FAILED, PaymentAttempt::UNCERTAIN, PaymentAttempt::CANCELLED], true)
            || ! in_array($code, ['initiation_unavailable', 'transport_unknown', 'cancelled_before_send'], true)
            || ($status === PaymentAttempt::UNCERTAIN) !== ($code === 'transport_unknown')
            || ($status === PaymentAttempt::CANCELLED) !== ($code === 'cancelled_before_send')) {
            $this->reject('نتیجه داخلی تلاش پرداخت معتبر نیست.');
        }

        return DB::transaction(function () use ($attempt, $status, $code): PaymentAttempt {
            $persisted = PaymentAttempt::query()->findOrFail($attempt->getKey());
            $this->lockedOrder($persisted->order);
            $attempt = PaymentAttempt::query()->whereKey($attempt)->lockForUpdate()->firstOrFail();
            if ($attempt->requested_at) {
                $this->reject('تلاش ارسال‌شده به درگاه فقط از مسیر بررسی معتبر قابل تعیین تکلیف است.');
            }
            if ($attempt->status === $status) {
                return $attempt;
            }
            if ($attempt->status !== PaymentAttempt::PENDING) {
                $this->reject('تغییر وضعیت این تلاش مجاز نیست؛ نتیجه نامشخص نیازمند تطبیق درگاه است.');
            }
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'status' => $status,
                'active_order_id' => $status === PaymentAttempt::UNCERTAIN ? $attempt->order_id : null,
                'diagnostics' => json_encode(['code' => $code], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $attempt->refresh();
        }, 3);
    }

    public function prepareGateway(Order $order, string $intent, ZarinpalConfiguration $configuration): PaymentAttempt
    {
        $configuration->assertConfigured();

        return DB::transaction(function () use ($order, $intent, $configuration): PaymentAttempt {
            $order = $this->lockedOrder($order);
            $this->assertPayable($order);
            if ($order->payment_method !== 'zarinpal') {
                $this->reject('روش پرداخت این سفارش زرین‌پال نیست.');
            }
            $key = 'zarinpal-'.$order->id.'-'.$intent;
            $existing = $order->paymentAttempts()->where('initiation_key', hash('sha256', $key))->first();
            if ($existing) {
                return $existing;
            }
            $active = $order->paymentAttempts()->whereNotNull('active_order_id')->first();
            if ($active) {
                return $active;
            }
            $last = $order->paymentAttempts()->latest('id')->first();
            if (($last && ($intent !== 'retry-'.$last->id || ! in_array($last->status, [PaymentAttempt::FAILED, PaymentAttempt::CANCELLED], true)))
                || (! $last && $intent !== 'initial')) {
                $this->reject('برای تلاش جدید، وضعیت تلاش قبلی و پیوند پرداخت را دوباره بررسی کنید.');
            }
            $amount = $configuration->gatewayAmount($this->pricing->amount($order->getAttributes()['total']));
            $attempt = $this->prepare($order, $key);
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'environment' => $configuration->environment(), 'merchant_context' => $configuration->context(),
                'gateway_amount' => $amount, 'gateway_amount_unit' => 'IRT', 'updated_at' => now(),
            ]);

            return $attempt->refresh();
        }, 3);
    }

    /** Claim exactly one outbound request. requested_at is never cleared, even after a timeout. */
    public function initiateGateway(PaymentAttempt $attempt): ?string
    {
        return $this->withAttempt($attempt, function (Order $order, PaymentAttempt $attempt): ?string {
            if ($attempt->provider !== 'zarinpal' || ! $attempt->merchant_context || ! $attempt->environment) {
                throw new LogicException('P3 requires a bound gateway attempt.');
            }
            $this->assertPayable($order);
            if ($attempt->requested_at || $attempt->authority || $attempt->status !== PaymentAttempt::PENDING) {
                return null;
            }
            $token = Str::random(64);
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'requested_at' => now(), 'operation' => 'initiate', 'operation_token' => $token,
                'operation_expires_at' => now()->addSeconds(90), 'updated_at' => now(),
            ]);

            return $token;
        });
    }

    public function finishGatewayInitiation(PaymentAttempt $attempt, string $token, ?string $authority, ?int $rejectionCode): PaymentAttempt
    {
        return $this->withAttempt($attempt, function (Order $order, PaymentAttempt $attempt) use ($token, $authority, $rejectionCode): PaymentAttempt {
            if ($attempt->operation !== 'initiate' || ! hash_equals($attempt->operation_token ?? '', $token)) {
                return $attempt;
            }
            $rejected = in_array($rejectionCode, [-9, -10, -11, -12, -13, -14, -15, -16, -17, -18, -19, -40, -41], true);
            $prefix = $attempt->environment === 'sandbox' ? 'S' : 'A';
            $valid = $authority !== null && preg_match('/^'.$prefix.'[A-Za-z0-9]{35}$/D', $authority) === 1;
            $status = $valid ? PaymentAttempt::PENDING : ($rejected ? PaymentAttempt::FAILED : PaymentAttempt::UNCERTAIN);
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'authority' => $valid ? $authority : null, 'status' => $status,
                'active_order_id' => $rejected && ! $valid ? null : $order->id,
                'operation' => null, 'operation_token' => null, 'operation_expires_at' => null,
                'diagnostics' => json_encode(['code' => $valid ? 'initiated' : ($rejected ? 'initiation_rejected' : 'initiation_unknown'),
                    'provider_code' => $rejectionCode], JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);

            return $attempt->refresh();
        });
    }

    public function claimGatewayCheck(PaymentAttempt $attempt, string $operation): ?string
    {
        if (! in_array($operation, ['verify', 'inquiry'], true)) {
            throw new LogicException('Unsupported payment check.');
        }

        return $this->withAttempt($attempt, function (Order $order, PaymentAttempt $attempt) use ($operation): ?string {
            if ($order->isPaid() || ! $attempt->authority || $attempt->active_order_id !== $order->id
                || ! in_array($attempt->status, [PaymentAttempt::PENDING, PaymentAttempt::UNCERTAIN], true)
                || ($attempt->operation_token && $attempt->operation_expires_at?->isFuture())) {
                return null;
            }
            $token = Str::random(64);
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'operation' => $operation, 'operation_token' => $token,
                'operation_expires_at' => now()->addSeconds(90), 'updated_at' => now(),
            ]);

            return $token;
        });
    }

    public function finishGatewayCheck(PaymentAttempt $attempt, string $token, string $status, string $diagnostic, ?int $providerCode = null): PaymentAttempt
    {
        if (! in_array($status, [PaymentAttempt::PENDING, PaymentAttempt::UNCERTAIN, PaymentAttempt::FAILED, PaymentAttempt::CANCELLED], true)
            || ! in_array($diagnostic, ['verification_rejected', 'verification_unknown', 'provider_in_bank', 'provider_paid', 'provider_failed', 'provider_reversed', 'inquiry_unknown', 'persistence_conflict'], true)) {
            throw new LogicException('Unsupported payment outcome.');
        }

        return $this->withAttempt($attempt, function (Order $order, PaymentAttempt $attempt) use ($token, $status, $diagnostic, $providerCode): PaymentAttempt {
            if (! hash_equals($attempt->operation_token ?? '', $token) || $attempt->status === PaymentAttempt::SUCCEEDED) {
                return $attempt;
            }
            $terminal = in_array($status, [PaymentAttempt::FAILED, PaymentAttempt::CANCELLED], true);
            if ($terminal && ($attempt->operation !== 'inquiry' || ! in_array($diagnostic, ['provider_failed', 'provider_reversed'], true))) {
                throw new LogicException('Only conclusive inquiry can release an initiated payment.');
            }
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'status' => $status, 'active_order_id' => $terminal ? null : $order->id,
                'operation' => null, 'operation_token' => null, 'operation_expires_at' => null,
                'diagnostics' => json_encode(['code' => $diagnostic, 'provider_code' => $providerCode], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $attempt->refresh();
        });
    }

    public function settleGateway(PaymentAttempt $attempt, array|ZarinpalVerification $result, ?string $token = null): PaymentAttempt
    {
        if (! $result instanceof ZarinpalVerification) {
            throw new LogicException('P3 requires validated server-side verification evidence.');
        }

        return $this->withAttempt($attempt, function (Order $order, PaymentAttempt $attempt) use ($result, $token): PaymentAttempt {
            if ($attempt->provider !== 'zarinpal' || $order->payment_method !== 'zarinpal'
                || $result->attemptId !== $attempt->id || $result->orderId !== $order->id
                || $result->environment !== $attempt->environment || $result->merchantContext !== $attempt->merchant_context
                || $result->authority !== $attempt->authority || $result->amount !== $attempt->gateway_amount
                || $result->unit !== 'IRT' || $attempt->gateway_amount_unit !== 'IRT' || $attempt->currency !== 'IRT'
                || ! in_array($result->code, [100, 101], true) || ! preg_match('/^[1-9][0-9]{0,39}$/D', $result->reference)
                || bccomp($this->pricing->amount($order->getAttributes()['total']), $attempt->amount, 2) !== 0
                || bccomp($attempt->amount, $result->amount, 2) !== 0) {
                $this->reject('مدرک تأیید درگاه با سفارش و تلاش پرداخت تطبیق ندارد.');
            }
            if ($attempt->status === PaymentAttempt::SUCCEEDED && $attempt->provider_reference === $result->reference && $order->isPaid()) {
                return $attempt;
            }
            if ($order->isPaid() || $attempt->active_order_id !== $order->id
                || ! in_array($attempt->status, [PaymentAttempt::PENDING, PaymentAttempt::UNCERTAIN], true)
                || $attempt->operation !== 'verify' || ! $token || ! hash_equals($attempt->operation_token ?? '', $token)) {
                $this->reject('این نتیجه برای عملیات جاری پرداخت معتبر نیست.');
            }
            $now = now();
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'status' => PaymentAttempt::SUCCEEDED, 'active_order_id' => null, 'settlement_order_id' => $order->id,
                'source' => 'gateway_verified', 'verification_outcome' => 'gateway_verified',
                'provider_reference' => $result->reference, 'verified_at' => $now,
                'operation' => null, 'operation_token' => null, 'operation_expires_at' => null,
                'diagnostics' => json_encode(['code' => 'verified', 'provider_code' => $result->code], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]);
            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'status' => $order->isPending() ? Order::STATUS_PAID : $order->status, 'updated_at' => $now,
            ]);
            DB::afterCommit(fn () => rescue(fn () => app(PaymentReceiptNotifier::class)->send($attempt->id), report: false));

            return $attempt->refresh();
        });
    }

    private function withAttempt(PaymentAttempt $attempt, \Closure $callback): mixed
    {
        return DB::transaction(function () use ($attempt, $callback) {
            $persisted = PaymentAttempt::query()->findOrFail($attempt->getKey());
            $order = $this->lockedOrder($persisted->order);
            $persisted = PaymentAttempt::query()->whereKey($persisted)->lockForUpdate()->firstOrFail();

            return $callback($order, $persisted);
        }, 3);
    }

    public function complete(Order $order, ?User $actor): bool
    {
        $this->authorizeAdministrator($actor);

        return DB::transaction(function () use ($order): bool {
            $order = $this->lockedOrder($order);
            if ($order->status === Order::STATUS_CANCELLED) {
                $this->reject('سفارش لغوشده قابل تکمیل نیست.');
            }
            if ($order->status === Order::STATUS_PAID && ! $order->isPaid()) {
                $this->reject('وضعیت پرداخت قدیمی ناسازگار است و باید پیش از تغییر تعیین تکلیف شود.');
            }
            DB::table('orders')->where('id', $order->id)->update(['status' => Order::STATUS_COMPLETED, 'updated_at' => now()]);

            return true;
        }, 3);
    }

    public function cancel(Order $order, ?User $actor): bool
    {
        $this->authorizeAdministrator($actor);

        return DB::transaction(function () use ($order): bool {
            $order = $this->lockedOrder($order);
            if (! $order->canBeCancelled()) {
                return false;
            }
            if ($order->status === Order::STATUS_PAID && ! $order->isPaid()) {
                $this->reject('وضعیت پرداخت قدیمی ناسازگار است و باید پیش از تغییر تعیین تکلیف شود.');
            }
            if ($order->paymentAttempts()->whereNotNull('active_order_id')->exists()) {
                $this->reject('پیش از لغو سفارش، تلاش پرداخت باز یا نامشخص را تعیین تکلیف کنید.');
            }
            // Cancellation is not a refund and does not remove payment evidence.
            DB::table('orders')->where('id', $order->id)->update(['status' => Order::STATUS_CANCELLED, 'updated_at' => now()]);

            return true;
        }, 3);
    }

    private function snapshot(Order $order): array
    {
        return [
            'order_id' => $order->id, 'provider' => $order->payment_method,
            'amount' => $this->pricing->amount($order->getAttributes()['total']),
            'currency' => ProductPricingService::CURRENCY, 'amount_unit' => ProductPricingService::CURRENCY,
        ];
    }

    private function lockedOrder(Order $order): Order
    {
        return Order::query()->whereKey($order)->lockForUpdate()->firstOrFail();
    }

    private function assertPayable(Order $order): void
    {
        if ($order->isPaid() || $order->status === Order::STATUS_PAID || $order->status === Order::STATUS_CANCELLED
            || $order->paymentAttempts()->whereNotNull('settlement_order_id')->exists()) {
            $this->reject('سفارش تسویه‌شده، دارای پرداخت قدیمی یا لغوشده قابل پرداخت مجدد نیست.');
        }
        if (! in_array($order->payment_method, ['manual', 'zarinpal'], true)
            || ! in_array($order->payment_status, [Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_STATUS_FAILED], true)
            || ! in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_COMPLETED], true)) {
            $this->reject('وضعیت یا روش پرداخت سفارش معتبر نیست.');
        }
        $amount = $this->pricing->amount($order->getAttributes()['total']);
        if (bccomp($amount, '0.00', 2) !== 1) {
            $this->reject('سفارش بدون مبلغ مثبت، نیازمند سیاست مستقل سفارش رایگان است.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
