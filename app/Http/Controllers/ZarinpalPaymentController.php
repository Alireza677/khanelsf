<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderConfirmationUrl;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZarinpalPaymentController extends Controller
{
    public function retry(Request $request, Order $order, ZarinpalPaymentGateway $gateway, OrderConfirmationUrl $confirmations): RedirectResponse
    {
        $confirmations->assertOwner($order);
        abort_unless($order->payment_method === 'zarinpal', 404);
        $intent = $request->query('intent', 'initial');
        abort_unless(is_string($intent) && preg_match('/^(initial|retry-[1-9][0-9]{0,19})$/D', $intent), 404);
        $confirmations->remember($order);
        $result = $gateway->createPayment($order, $intent);

        return $result['redirect_url']
            ? redirect()->away($result['redirect_url'])
            : redirect()->to($confirmations->temporary($order));
    }

    public function reconcile(Order $order, ZarinpalPaymentGateway $gateway, OrderConfirmationUrl $confirmations): RedirectResponse
    {
        $confirmations->assertOwner($order);
        abort_unless($order->payment_method === 'zarinpal', 404);
        $gateway->reconcilePayment($order);

        return redirect()->to($confirmations->temporary($order));
    }
}
