@extends('layouts.app')

@section('content')
    <section class="checkout-page">
        <h1>نتیجه پرداخت</h1>

        <div class="cart-summary">
            <p>برای مشاهده نتیجه معتبر پرداخت، رسید امضاشده سفارش یا تاریخچه سفارش‌های حساب خود را باز کنید.</p>
        </div>

        <p><a class="button" href="{{ route('shop.index') }}">بازگشت به فروشگاه</a></p>
    </section>
@endsection
