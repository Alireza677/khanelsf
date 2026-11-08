@php
    $freshness = app(\App\Services\ProductPriceFreshness::class);
    $pricePresentation = $pricePresentation ?? $freshness->presentation($product);
    $controlled = $pricePresentation['enabled'];
@endphp
@if ($controlled)
    @if ($pricePresentation['show_verified_at'] && $product->price_verified_at)
        <p>آخرین تأیید قیمت: <x-persian-date :value="$product->price_verified_at" format="datetime" /></p>
    @endif
    @if ($pricePresentation['blocked'])
        <div class="empty-state" role="note" dir="rtl">
            @if ($pricePresentation['status'] === 'expired')
                <p>اعتبار این قیمت به پایان رسیده و ممکن است تغییر کرده باشد. برای دریافت قیمت به‌روز درخواست استعلام ثبت کنید.</p>
            @else
                <p>قیمت این محصول هنوز تأیید نشده یا تعیین نشده است. برای دریافت قیمت به‌روز استعلام بگیرید.</p>
            @endif
            @if (empty($isPreview) && empty($context['isPreview']) && ($inquiryUrl = $pricePresentation['inquiry_url']))
                <a class="button button-secondary" href="{{ $inquiryUrl }}">{{ str_starts_with($inquiryUrl, 'tel:') ? 'تماس برای استعلام قیمت' : 'درخواست استعلام قیمت' }}</a>
            @endif
        </div>
    @endif
@endif
