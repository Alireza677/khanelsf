<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class OrderPrintController extends Controller
{
    public function __invoke(Order $order, SettingsService $settings): View
    {
        abort_unless(Auth::user()?->is_admin, 403);

        return view('admin.orders.print', [
            'order' => $order->load('items'),
            'siteName' => $settings->siteName(),
            'logoUrl' => $settings->logoUrl(),
        ]);
    }
}
