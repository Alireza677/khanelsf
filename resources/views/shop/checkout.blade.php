@extends('layouts.app')

@section('content')
    @php($pricing = app(\App\Services\ProductPricingService::class))
    <section class="checkout-page">
        <h1>تسویه حساب</h1>

        @error('cart') <p class="form-error" role="alert">{{ $message }}</p> @enderror
        @error('checkout') <p class="form-error" role="alert">{{ $message }}</p> @enderror
        @error('payment') <p class="form-error" role="alert">{{ $message }}</p> @enderror

        <div class="cart-table">
            @foreach ($cartItems as $item)
                <div class="cart-row">
                    <strong>{{ $item['title'] }}</strong>
                    <span>{{ $item['quantity'] }} × {{ $pricing->formatAmount($item['unit_price']) }} تومان</span>
                    <span>{{ $pricing->formatAmount($item['total']) }} تومان</span>
                </div>
            @endforeach
        </div>

        <div class="cart-summary">
            <strong>جمع کل: {{ $pricing->formatAmount($subtotal) }} تومان</strong>
        </div>

        <form class="form-card" method="post" action="{{ route('checkout.store') }}">
            @csrf
            <input type="hidden" name="_cart_review" value="{{ $cartReview }}">
            <input type="hidden" name="_checkout_intent" value="{{ $checkoutIntent }}">

            <div>
                <label for="customer_name">نام</label>
                <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name') }}" required>
                <p class="field-help">نامی که برای پیگیری سفارش استفاده می‌شود.</p>
                @error('customer_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="customer_phone">تلفن</label>
                <input id="customer_phone" name="customer_phone" type="text" value="{{ old('customer_phone') }}" required>
                <p class="field-help">برای تایید و پیگیری سفارش الزامی است.</p>
                @error('customer_phone') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="customer_email">ایمیل</label>
                <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}">
                <p class="field-help">اختیاری است. برای دریافت ایمیل تایید سفارش وارد کنید.</p>
                @error('customer_email') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="customer_address">آدرس</label>
                <textarea id="customer_address" name="customer_address" rows="4">{{ old('customer_address') }}</textarea>
                <p class="field-help">اختیاری است. برای آدرس ارسال یا محل ارائه خدمات استفاده می‌شود.</p>
                @error('customer_address') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="notes">توضیحات</label>
                <textarea id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>
                <p class="field-help">زمان مناسب تماس یا توضیحات تکمیلی را وارد کنید.</p>
                @error('notes') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <button class="button" type="submit">ثبت سفارش</button>
        </form>
    </section>
@endsection
