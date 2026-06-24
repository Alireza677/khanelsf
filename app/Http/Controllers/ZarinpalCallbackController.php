<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ZarinpalCallbackController extends Controller
{
    public function __invoke(Request $request, ZarinpalPaymentGateway $gateway): View
    {
        $validated = $request->validate([
            'order' => ['nullable', 'string', 'max:255'],
            'Authority' => ['nullable', 'string', 'max:255'],
            'Status' => ['nullable', 'string', 'max:255'],
        ]);

        $order = filled($validated['order'] ?? null)
            ? Order::query()->where('order_number', $validated['order'])->first()
            : null;

        $verification = $order
            ? $gateway->verifyPayment($order, $validated)
            : [
                'ok' => false,
                'verified' => false,
                'error' => 'سفارش برای تایید پرداخت پیدا نشد.',
            ];

        return view('shop.payment-callback', [
            'gateway' => 'zarinpal',
            'order' => $order,
            'verification' => $verification,
        ]);
    }
}
