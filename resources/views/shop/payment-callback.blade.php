@extends('layouts.app')

@section('content')
    <section class="checkout-page">
        <h1>نتیجه پرداخت</h1>

        <div class="cart-summary">
            <p><strong>درگاه:</strong> {{ ucfirst($gateway) }}</p>

            @if ($order)
                <p><strong>شماره سفارش:</strong> {{ $order->order_number }}</p>
                <p><strong>وضعیت:</strong> {{ ucfirst($order->status) }}</p>
                <p><strong>پرداخت:</strong> {{ ucfirst($order->payment_status) }}</p>
            @endif

            <p>{{ $verification['error'] ?? 'تایید پرداخت هنوز در حال پیاده‌سازی است.' }}</p>
        </div>

        <p><a class="button" href="{{ route('shop.index') }}">بازگشت به فروشگاه</a></p>
    </section>
@endsection
