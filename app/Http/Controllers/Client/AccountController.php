<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PublicAccountNavigation;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __invoke(PublicAccountNavigation $navigation): View
    {
        $account = $navigation->present();

        return view('client.account', [
            'account' => $account,
            'hasOrders' => Order::query()->where('user_id', $account['user']->getKey())->exists(),
        ]);
    }
}
