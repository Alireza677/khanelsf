@extends('layouts.account')

@section('account-content')
    <section class="account-orders account-order-detail">
        <header class="account-orders__header">
            <div>
                <p class="public-account-home__eyebrow">سفارش‌های من</p>
                <h1>سفارش {{ $order->order_number }}</h1>
            </div>
            <a class="button button-secondary" href="{{ route('account.orders.index') }}">بازگشت به سفارش‌ها</a>
        </header>

        <div class="account-order-detail__summary">
            <div><small>تاریخ</small><strong><x-persian-date :value="$order->created_at" /></strong></div>
            <div><small>وضعیت</small><strong>{{ $order->statusLabel() }}</strong></div>
            <div><small>وضعیت پرداخت</small><strong>{{ $order->paymentStatusLabel() }}</strong></div>
            <div><small>مبلغ کل</small><strong>{{ number_format((float) $order->total) }} تومان</strong></div>
        </div>

        <div class="account-order-detail__contact">
            <h2>اطلاعات ثبت‌شده سفارش</h2>
            <p><strong>نام:</strong> {{ $order->customer_name }}</p>
            <p><strong>تلفن:</strong> <span dir="ltr">{{ $order->customer_phone }}</span></p>
            @if ($order->customer_email)<p><strong>ایمیل:</strong> {{ $order->customer_email }}</p>@endif
            @if ($order->customer_address)<p><strong>آدرس:</strong> {{ $order->customer_address }}</p>@endif
        </div>

        <div class="account-order-detail__items">
            <h2>اقلام سفارش</h2>
            @foreach ($order->items as $item)
                <div class="account-order-detail__item">
                    <strong>{{ $item->product_title }}</strong>
                    <span>{{ $item->quantity }} عدد</span>
                    <span>{{ number_format((float) $item->total) }} تومان</span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
