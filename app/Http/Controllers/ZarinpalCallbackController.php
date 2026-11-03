<?php

namespace App\Http\Controllers;

use App\Services\OrderConfirmationUrl;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZarinpalCallbackController extends Controller
{
    public function __invoke(Request $request, ZarinpalPaymentGateway $gateway, OrderConfirmationUrl $confirmations): View|RedirectResponse
    {
        $authority = $request->input('Authority');
        $context = $request->input('ctx');
        $status = $request->input('Status');
        if (is_string($authority) && is_string($context) && in_array($status, ['OK', 'NOK'], true)) {
            $attempt = $gateway->callbackAttempt($authority, $context);
            if ($attempt) {
                $gateway->verifyPayment($attempt->order, ['Authority' => $authority, 'ctx' => $context, 'Status' => $status]);
                if ($confirmations->canReturnToReceipt($attempt->order->fresh())) {
                    return redirect()->to($confirmations->temporary($attempt->order));
                }
            }
        }

        // Identical public response: no Order number, existence, status, PII or reference.
        return view('shop.payment-callback');
    }
}
