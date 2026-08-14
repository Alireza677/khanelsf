<?php

namespace App\Services;

use App\Models\Order;
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
}
