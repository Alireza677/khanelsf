@extends('layouts.app')

@section('content')
    <section class="checkout-page">
        <h1>پیگیری سفارش قدیمی</h1>
        <div class="cart-summary">
            <p>اطلاعات خصوصی این سفارش از طریق لینک رسید نمایش داده نمی‌شود.</p>
            <p>برای پیگیری سفارش یا پرداخت، با پشتیبانی تماس بگیرید و کد سفارش خود را اعلام کنید.</p>
        </div>
        <p><a class="button" href="{{ route('contact.create') }}">ارتباط با پشتیبانی</a></p>
        <p><a href="{{ route('account.orders.index') }}">سفارش‌های حساب من</a></p>
    </section>
@endsection
