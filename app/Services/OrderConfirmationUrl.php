<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class OrderConfirmationUrl
{
    public function temporary(Order $order): string
    {
        return URL::temporarySignedRoute(
            'checkout.thank-you',
            now()->addDay(),
            ['order' => $order],
        );
    }

    public function remember(Order $order): void
    {
        $orders = session('shop_order_receipts', []);
        $orders[$order->id] = true;
        session(['shop_order_receipts' => array_slice($orders, -20, null, true)]);
    }

    public function canReturnToReceipt(Order $order): bool
    {
        $user = Auth::guard('client')->user()?->fresh();

        return $order->user_id !== null
            ? $user && ! $user->is_admin && $user->isActive() && $user->id === $order->user_id
            : session('shop_order_receipts.'.$order->id) === true;
    }

    public function assertOwner(Order $order): void
    {
        // Legacy payment actions retain the original browser capability, not just a leaked URL.
        abort_unless($this->canReturnToReceipt($order), 404);
    }

    public function paymentAction(Order $order, string $action, string $intent = 'initial'): string
    {
        return URL::temporarySignedRoute('payments.zarinpal.'.$action, now()->addDay(), ['order' => $order, 'intent' => $intent]);
    }
}
