@extends('layouts.app')

@section('content')
    <section class="checkout-page">
        <h1>نتیجه پرداخت</h1>

        <div class="cart-summary">
            <p>برای مشاهده نتیجه پرداخت، وارد حساب خود شوید و بخش سفارش‌های من را باز کنید. برای پیگیری سفارش‌های قدیمی با پشتیبانی تماس بگیرید.</p>
        </div>

        <p><a class="button" href="{{ route('account.orders.index') }}">سفارش‌های من</a></p>
        <p><a href="{{ route('shop.index') }}">بازگشت به فروشگاه</a></p>
    </section>
@endsection
