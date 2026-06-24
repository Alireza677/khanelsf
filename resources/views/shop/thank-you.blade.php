@extends('layouts.app')

@section('content')
    <section class="checkout-page">
        <h1>سفارش دریافت شد</h1>

        @if (session('success'))
            <p class="form-status" role="status">{{ session('success') }}</p>
        @endif

        <div class="cart-summary">
            <p><strong>شماره سفارش:</strong> {{ $order->order_number }}</p>
            <p><strong>وضعیت:</strong> {{ ucfirst($order->status) }}</p>
            <p><strong>پرداخت:</strong> {{ ucfirst($order->payment_status) }}</p>
            <p><strong>مبلغ کل:</strong> {{ number_format((float) $order->total) }} تومان</p>
            <p>{{ $manualPaymentMessage }}</p>
        </div>

        <div class="cart-summary">
            <h2>اطلاعات مشتری</h2>
            <p><strong>نام:</strong> {{ $order->customer_name }}</p>
            <p><strong>تلفن:</strong> {{ $order->customer_phone }}</p>

            @if ($order->customer_email)
                <p><strong>ایمیل:</strong> {{ $order->customer_email }}</p>
            @endif

            @if ($order->customer_address)
                <p><strong>آدرس:</strong> {{ $order->customer_address }}</p>
            @endif

            @if ($order->notes)
                <p><strong>توضیحات:</strong> {{ $order->notes }}</p>
            @endif
        </div>

        <div class="cart-table">
            @foreach ($order->items as $item)
                <div class="cart-row">
                    <div><strong>{{ $item->product_title }}</strong></div>
                    <div>{{ number_format((float) $item->unit_price) }} تومان</div>
                    <div>تعداد: {{ $item->quantity }}</div>
                    <div>{{ number_format((float) $item->total) }} تومان</div>
                </div>
            @endforeach
        </div>

        <p><a class="button" href="{{ route('shop.index') }}">ادامه خرید</a></p>
    </section>
@endsection
