@extends('layouts.app')

@section('content')
    @php($pricing = app(\App\Services\ProductPricingService::class))
    <section class="checkout-page">
        <h1>سفارش دریافت شد</h1>

        @if (session('success'))
            <p class="form-status" role="status">{{ session('success') }}</p>
        @endif

        <div class="cart-summary">
            <p><strong>شماره سفارش:</strong> {{ $order->order_number }}</p>
            <p><strong>وضعیت:</strong> {{ $order->statusLabel() }}</p>
            <p><strong>پرداخت:</strong> {{ $order->paymentStatusLabel() }}</p>
            <p><strong>مبلغ کل:</strong> {{ $pricing->formatAmount($order->total) }} تومان</p>
            <p>{{ $manualPaymentMessage }}</p>
        </div>

        @if ($order->payment_method === 'zarinpal')
            <div class="cart-summary">
                <h2>وضعیت پرداخت</h2>
                @if ($paymentAttempt?->source === 'gateway_verified' && $order->isPaid())
                    <p>پرداخت تأیید شد.</p>
                    @if ($paymentAttempt->environment === 'sandbox')
                        <p>این پرداخت آزمایشی (Sandbox) است و وجه واقعی دریافت نشده است.</p>
                    @endif
                    <p>مرجع درگاه: {{ $paymentAttempt->provider_reference }}</p>
                    <p>مبلغ پرداخت: {{ $pricing->formatAmount($paymentAttempt->amount) }} تومان ({{ $paymentAttempt->currency }})</p>
                    <p>زمان تأیید: {{ $paymentAttempt->verified_at?->format('Y-m-d H:i:s') }}</p>
                    <p>تأیید پرداخت به معنی تکمیل یا تحویل سفارش نیست.</p>
                @elseif ($order->isPaid())
                    <p>{{ $order->paymentProvenanceLabel() }}</p>
                @elseif ($paymentAttempt?->status === 'uncertain' || ($paymentAttempt?->requested_at && ! $paymentAttempt?->authority))
                    <p>نتیجه پرداخت نامشخص؛ نیازمند بررسی است. پرداخت جدید را تا تعیین تکلیف آغاز نکنید.</p>
                    @if (! $paymentAttempt?->authority)
                        <p>پاسخ درخواست درگاه دریافت نشده است؛ برای پیگیری با پشتیبانی سایت تماس بگیرید.</p>
                    @endif
                @elseif (in_array($paymentAttempt?->status, ['failed', 'cancelled'], true))
                    <p>پرداخت ناموفق؛ سفارش همچنان پرداخت‌نشده است.</p>
                @else
                    <p>سفارش ثبت شد و در انتظار پرداخت است.</p>
                @endif
                @if ($paymentRetryUrl)
                    <form method="post" action="{{ $paymentRetryUrl }}">
                        @csrf
                        <button class="button" type="submit">{{ $paymentAttempt?->authority && $paymentAttempt?->status === 'pending' ? 'ادامه پرداخت' : 'تلاش دوباره برای پرداخت' }}</button>
                    </form>
                @endif
                @if ($paymentReconcileUrl)
                    <form method="post" action="{{ $paymentReconcileUrl }}">
                        @csrf
                        <button class="button" type="submit">بررسی وضعیت پرداخت</button>
                    </form>
                @endif
            </div>
        @endif

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

        </div>

        <div class="cart-table">
            @foreach ($order->items as $item)
                <div class="cart-row">
                    <div><strong>{{ $item->product_title }}</strong></div>
                    <div>{{ $pricing->formatAmount($item->unit_price) }} تومان</div>
                    <div>تعداد: {{ $item->quantity }}</div>
                    <div>{{ $pricing->formatAmount($item->total) }} تومان</div>
                </div>
            @endforeach
        </div>

        <p><a class="button" href="{{ route('shop.index') }}">ادامه خرید</a></p>
    </section>
@endsection
