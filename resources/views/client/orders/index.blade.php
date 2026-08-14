@extends('layouts.account')

@section('account-content')
    <section class="account-orders">
        <header class="account-orders__header">
            <div>
                <p class="public-account-home__eyebrow">حساب کاربری</p>
                <h1>سفارش‌های من</h1>
            </div>
            <a class="button button-secondary" href="{{ route('account.home') }}">بازگشت به حساب کاربری</a>
        </header>

        @if ($orders->isEmpty())
            <div class="account-orders__empty">
                <h2>هنوز سفارشی ثبت نکرده‌اید.</h2>
                <p>پس از ثبت خرید، سفارش‌های شما در این بخش نمایش داده می‌شوند.</p>
                <a class="button" href="{{ route('shop.index') }}">مشاهده فروشگاه</a>
            </div>
        @else
            <div class="account-orders__list">
                @foreach ($orders as $order)
                    <article class="account-order-card">
                        <div><small>شماره سفارش</small><strong>{{ $order->order_number }}</strong></div>
                        <div><small>تاریخ</small><span>{{ $order->created_at->format('Y/m/d') }}</span></div>
                        <div><small>وضعیت</small><span>{{ $order->statusLabel() }}</span></div>
                        <div><small>وضعیت پرداخت</small><span>{{ $order->paymentStatusLabel() }}</span></div>
                        <div><small>تعداد اقلام</small><span>{{ $order->items_count }}</span></div>
                        <div><small>مبلغ کل</small><strong>{{ number_format((float) $order->total) }} تومان</strong></div>
                        <a class="button button-secondary" href="{{ route('account.orders.show', $order) }}">مشاهده جزئیات</a>
                    </article>
                @endforeach
            </div>
            <div class="account-orders__pagination">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
