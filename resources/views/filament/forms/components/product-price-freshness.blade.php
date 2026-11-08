@php
    $product = $getRecord();
    $freshness = app(\App\Services\ProductPriceFreshness::class);
@endphp
@if ($product?->exists)
    @php
        $expiry = $freshness->expiresAt($product);
        $days = $freshness->days($product);
        $remaining = $expiry ? (int) ceil(now()->diffInSeconds($expiry, false) / 86400) : null;
    @endphp
    <div class="space-y-3" dir="rtl">
        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div><dt>قیمت اصلی / فروش ویژه (تومان)</dt><dd>{{ $product->price }} / {{ $product->sale_price ?? '—' }}</dd></div>
            <div><dt>وضعیت قیمت</dt><dd>{{ \App\Services\ProductPriceFreshness::LABELS[$freshness->status($product)] }}</dd></div>
            <div><dt>آخرین تغییر قیمت</dt><dd>{{ \App\Support\PersianDate::dateTime($product->price_changed_at) ?? 'ثبت نشده' }}</dd></div>
            <div><dt>آخرین تأیید قیمت</dt><dd>{{ \App\Support\PersianDate::dateTime($product->price_verified_at) ?? 'تأیید نشده' }}</dd></div>
            <div><dt>اعتبار مؤثر</dt><dd>{{ $days === null ? 'نامحدود' : $days.' روز' }}</dd></div>
            <div><dt>انقضا</dt><dd>{{ \App\Support\PersianDate::dateTime($expiry) ?? '—' }}</dd></div>
            @if ($remaining !== null)
                <div><dt>زمان اعتبار</dt><dd>{{ $remaining > 0 ? $remaining.' روز باقی‌مانده' : ($remaining === 0 ? 'کمتر از یک روز از انقضا گذشته' : abs($remaining).' روز از انقضا گذشته') }}</dd></div>
            @endif
        </dl>
        @unless ($freshness->enabled())<p>کنترل خرید براساس اعتبار قیمت غیرفعال است.</p>@endunless
        <details>
            <summary>۱۰ رویداد اخیر قیمت</summary>
            <ul class="space-y-2">
                @foreach ($product->priceHistories()->limit(10)->get() as $event)
                    <li>{{ \App\Support\PersianDate::dateTime($event->occurred_at) }} — {{ $event->event === 'price_changed' ? 'تغییر قیمت' : 'تأیید قیمت' }}؛ {{ $event->old_price ?? '—' }} ← {{ $event->new_price }}؛ فروش ویژه: {{ $event->old_sale_price ?? '—' }} ← {{ $event->new_sale_price ?? '—' }}؛ کاربر: {{ $event->user_id ?? 'سیستم' }}؛ منبع: {{ $event->source }}</li>
                @endforeach
            </ul>
        </details>
    </div>
@else
    <p>پس از ذخیره محصول، تأیید قیمت و سابقه در دسترس خواهد بود.</p>
@endif
