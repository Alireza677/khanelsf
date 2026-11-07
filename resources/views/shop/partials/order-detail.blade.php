@php
    $pricing = app(\App\Services\ProductPricingService::class);
    $settings = app(\App\Services\SettingsService::class);
    $isCancelled = $order->status === \App\Models\Order::STATUS_CANCELLED;
    $isCompleted = $order->status === \App\Models\Order::STATUS_COMPLETED;
@endphp
<section class="order-receipt" aria-labelledby="order-heading">
    <header class="order-receipt__heading">
        <div>
            <p class="order-receipt__eyebrow">{{ $inAccount ? 'حساب کاربری / سفارش‌های من' : 'رسید سفارش' }}</p>
            <h1 id="order-heading">{{ $isReceipt ? 'سفارش دریافت شد' : 'جزئیات سفارش' }}</h1>
            <p>اطلاعات خرید و آخرین وضعیت سفارش شما در یک نگاه</p>
        </div>
        @if ($inAccount)
            <a class="order-button order-button--secondary" href="{{ route('account.orders.index') }}">سفارش‌های من @svg('heroicon-o-arrow-left', 'order-icon', ['aria-hidden' => 'true'])</a>
        @endif
    </header>

    @if (session('success'))
        <p class="order-notice" role="status">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <div class="order-notice order-notice--danger" role="alert" tabindex="-1" data-order-errors>
            <h2>عملیات انجام نشد</h2>
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="order-panel order-receipt__hero">
        <div class="order-receipt__state">
            <span class="order-receipt__state-icon order-badge--{{ $order->statusColor() }}">@svg($order->statusIcon(), 'order-icon', ['aria-hidden' => 'true'])</span>
            <div>
                <div class="order-receipt__badges">
                    <span><small>سفارش</small> <x-order-status :order="$order" /></span>
                    <span><small>پرداخت</small> <x-order-status :order="$order" payment /></span>
                </div>
                <h2>{{ $isCancelled ? 'این سفارش لغو شده است' : ($isCompleted ? 'سفارش شما تکمیل شده است' : ($order->isPaid() ? 'پرداخت شما ثبت شده است' : 'سفارش شما ثبت شده است')) }}</h2>
                <p>{{ $isCancelled ? 'لغو سفارش به معنی استرداد وجه نیست. برای پیگیری با پشتیبانی تماس بگیرید.' : ($isCompleted ? ($order->isPaid() ? 'برای پیگیری جزئیات خرید، کد سفارش را نزد خود نگه دارید.' : 'تکمیل سفارش به معنی تأیید پرداخت نیست؛ پرداخت این سفارش هنوز تأیید نشده است.') : ($order->isPaid() ? 'تأیید پرداخت به معنی تکمیل یا تحویل سفارش نیست.' : 'پس از تأیید پرداخت، وضعیت پرداخت در همین صفحه به‌روز می‌شود.')) }}</p>
            </div>
        </div>
        <div class="order-receipt__code" data-order-copy>
            <label for="public-order-code">کد پیگیری سفارش</label>
            <div>
                <input id="public-order-code" type="text" dir="ltr" value="{{ $order->displayCode() }}" readonly aria-describedby="order-copy-feedback" data-order-code>
                <button class="order-button order-button--secondary" type="button" aria-label="کپی کد سفارش" data-copy-order-code>
                    @svg('heroicon-o-square-2-stack', 'order-icon', ['aria-hidden' => 'true']) <span data-copy-label>کپی کد</span>
                </button>
            </div>
            <p id="order-copy-feedback" role="status" aria-live="polite" aria-atomic="true" data-copy-feedback>برای پیگیری، این کد را به پشتیبانی اعلام کنید.</p>
        </div>
        <dl class="order-receipt__facts">
            <div><dt>مبلغ کل سفارش</dt><dd class="order-receipt__amount">{{ $pricing->formatAmount($order->total) }} <small>تومان</small></dd></div>
            <div><dt>تاریخ ثبت</dt><dd><x-persian-date :value="$order->created_at" /></dd></div>
            <div><dt>روش پرداخت</dt><dd>{{ $order->paymentMethodLabel() }}</dd></div>
        </dl>
    </div>

    <div class="order-receipt__grid">
        <div class="order-receipt__main">
            <section class="order-panel" aria-labelledby="order-items-heading">
                <div class="order-panel__heading"><h2 id="order-items-heading">اقلام سفارش</h2><span>{{ $order->items->count() }} قلم</span></div>
                <ul class="order-receipt__items">
                    @foreach ($order->items as $item)
                        <li>
                            <span class="order-receipt__item-icon">@svg('heroicon-o-shopping-bag', 'order-icon', ['aria-hidden' => 'true'])</span>
                            <div class="order-receipt__item-name"><h3>{{ $item->product_title }}</h3><p>{{ $pricing->formatAmount($item->unit_price) }} تومان <span>× {{ $item->quantity }} عدد</span></p>@if ($item->product_sku)<small>کد کالا: <bdi>{{ $item->product_sku }}</bdi></small>@endif</div>
                            <strong>{{ $pricing->formatAmount($item->total) }} <small>تومان</small></strong>
                        </li>
                    @endforeach
                </ul>
                <dl class="order-receipt__totals"><div><dt>جمع اقلام</dt><dd>{{ $pricing->formatAmount($order->subtotal) }} تومان</dd></div><div><dt>مبلغ کل سفارش</dt><dd>{{ $pricing->formatAmount($order->total) }} تومان</dd></div></dl>
            </section>

            <section class="order-panel" aria-labelledby="order-payment-heading">
                <div class="order-panel__heading"><h2 id="order-payment-heading">اطلاعات پرداخت</h2><x-order-status :order="$order" payment /></div>
                @if ($order->payment_method === 'zarinpal')
                    @if ($paymentAttempt?->source === 'gateway_verified' && $order->isPaid())
                        <p>پرداخت تأیید شد.</p>
                        @if ($paymentAttempt->environment === 'sandbox')<p class="order-notice">این پرداخت آزمایشی (Sandbox) است و وجه واقعی دریافت نشده است.</p>@endif
                        <dl class="order-receipt__contact">
                            <div><dt>مرجع درگاه</dt><dd><bdi>{{ $paymentAttempt->provider_reference }}</bdi></dd></div>
                            <div><dt>مبلغ پرداخت</dt><dd>{{ $pricing->formatAmount($paymentAttempt->amount) }} تومان</dd></div>
                            <div><dt>زمان تأیید</dt><dd><x-persian-date :value="$paymentAttempt->verified_at" /></dd></div>
                        </dl>
                    @elseif ($order->isPaid())
                        <p>{{ $order->paymentProvenanceLabel() }}</p>
                    @elseif ($paymentAttempt?->status === 'uncertain' || ($paymentAttempt?->requested_at && ! $paymentAttempt?->authority))
                        <p class="order-notice">نتیجه پرداخت نامشخص؛ نیازمند بررسی است. پرداخت جدید را تا تعیین تکلیف آغاز نکنید.</p>
                        @if (! $paymentAttempt?->authority)<p>پاسخ درخواست درگاه دریافت نشده است؛ برای پیگیری با پشتیبانی سایت تماس بگیرید.</p>@endif
                    @elseif (in_array($paymentAttempt?->status, ['failed', 'cancelled'], true))
                        <p>پرداخت ناموفق؛ سفارش همچنان پرداخت‌نشده است.</p>
                    @else
                        <p>سفارش ثبت شد و در انتظار پرداخت است.</p>
                    @endif
                @elseif ($order->isPaid())
                    <p>{{ $order->paymentProvenanceLabel() }}</p>
                @endif
                @if (! $order->isPaid() && ! $isCancelled)<p class="order-receipt__instructions">{{ $manualPaymentMessage }}</p>@endif
                <div class="order-receipt__actions">
                    @if ($paymentRetryUrl)
                        <form method="post" action="{{ $paymentRetryUrl }}" data-order-payment-form>@csrf<button class="order-button" type="submit">{{ $paymentAttempt?->authority && $paymentAttempt?->status === 'pending' ? 'ادامه پرداخت' : 'تلاش دوباره برای پرداخت' }}</button></form>
                    @endif
                    @if ($paymentReconcileUrl)
                        <form method="post" action="{{ $paymentReconcileUrl }}" data-order-payment-form>@csrf<button class="order-button order-button--secondary" type="submit">بررسی وضعیت پرداخت</button></form>
                    @endif
                </div>
            </section>
        </div>

        <aside class="order-receipt__aside" aria-label="اطلاعات مشتری و پشتیبانی">
            <section class="order-panel">
                <div class="order-panel__heading"><h2>اطلاعات مشتری</h2>@svg('heroicon-o-user-circle', 'order-icon', ['aria-hidden' => 'true'])</div>
                <dl class="order-receipt__contact">
                    <div><dt>نام و نام خانوادگی</dt><dd>{{ $order->customer_name }}</dd></div>
                    <div><dt>تلفن</dt><dd><bdi>{{ $order->customer_phone }}</bdi></dd></div>
                    @if ($order->customer_email)<div><dt>ایمیل</dt><dd><bdi>{{ $order->customer_email }}</bdi></dd></div>@endif
                    @if ($order->customer_address)<div><dt>نشانی ثبت‌شده</dt><dd>{{ $order->customer_address }}</dd></div>@endif
                    @if ($order->notes)<div><dt>توضیحات شما</dt><dd class="order-receipt__instructions">{{ $order->notes }}</dd></div>@endif
                </dl>
            </section>
            <section class="order-panel order-receipt__support">
                @svg('heroicon-o-chat-bubble-left-right', 'order-icon', ['aria-hidden' => 'true'])
                <h2>برای پیگیری همراه شما هستیم</h2>
                <p>کد سفارش را آماده داشته باشید تا سریع‌تر راهنمایی‌تان کنیم.</p>
                <a class="order-button order-button--secondary" href="{{ route('contact.create') }}">تماس با پشتیبانی</a>
                @if ($settings->contactPhone())<a class="order-receipt__phone" dir="ltr" href="tel:{{ $settings->contactPhone() }}">{{ $settings->contactPhone() }}</a>@endif
            </section>
        </aside>
    </div>
    <footer class="order-receipt__footer">
        <p>کد سفارش را برای پیگیری‌های بعدی نگه دارید.</p>
        <div class="order-receipt__actions">
            @if ($inAccount)<a class="order-button order-button--secondary" href="{{ route('account.orders.index') }}">مشاهده سفارش‌های من</a>@endif
            <a class="order-button" href="{{ route('shop.index') }}">ادامه خرید @svg('heroicon-o-arrow-left', 'order-icon', ['aria-hidden' => 'true'])</a>
        </div>
    </footer>
</section>
@include('shop.partials.order-interactions')
