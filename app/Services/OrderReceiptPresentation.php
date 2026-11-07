<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentAttempt;

final class OrderReceiptPresentation
{
    /** Presentation only; callers must enforce signed receipt or PublicOrderAccess first. */
    public function forOrder(Order $order): array
    {
        $attempt = $order->paymentAttempts()->latest('id')->first();
        $retryable = ! $order->isPaid() && $order->payment_method === 'zarinpal'
            && $order->status !== Order::STATUS_CANCELLED
            && (! $attempt || in_array($attempt->status, [PaymentAttempt::FAILED, PaymentAttempt::CANCELLED], true)
                || ($attempt->status === PaymentAttempt::PENDING && $attempt->authority));
        $confirmations = app(OrderConfirmationUrl::class);

        return [
            'order' => $order->loadMissing('items'),
            'inAccount' => app(PublicAccountNavigation::class)->present()['authenticated'],
            'manualPaymentMessage' => $order->payment_method === 'manual'
                ? app(ManualPaymentGateway::class)->instructions() : app(ZarinpalPaymentGateway::class)->instructions(),
            'paymentAttempt' => $attempt,
            'paymentRetryUrl' => $retryable ? $confirmations->paymentAction($order, 'retry', $attempt && in_array($attempt->status, [PaymentAttempt::FAILED, PaymentAttempt::CANCELLED], true) ? 'retry-'.$attempt->id : 'initial') : null,
            'paymentReconcileUrl' => ! $order->isPaid() && $attempt?->authority && $order->payment_method === 'zarinpal'
                ? $confirmations->paymentAction($order, 'reconcile') : null,
        ];
    }
}
