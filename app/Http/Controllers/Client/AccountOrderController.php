<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\PublicOrderAccess;
use App\Services\OrderReceiptPresentation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountOrderController extends Controller
{
    public function index(Request $request, PublicOrderAccess $orders): View
    {
        return view('client.orders.index', [
            'orders' => $orders->paginateFor($request->user('client')),
        ]);
    }

    public function show(Request $request, int $order, PublicOrderAccess $orders, OrderReceiptPresentation $receipt): View
    {
        return view('client.orders.show', $receipt->forOrder($orders->findFor($request->user('client'), $order)));
    }
}
