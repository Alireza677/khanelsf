@php
    $freshness = app(\App\Services\ProductPriceFreshness::class);
    $pricing = app(\App\Services\ProductPricingService::class);
    $pricePresentation = $pricePresentation ?? $freshness->presentation($product, withInquiry: false);
    $priceState = $pricePresentation['status'];
    $displayPrice = fn (string $amount) => ! empty($persianPriceDigits)
        ? \App\Support\PersianDate::digits($pricing->formatAmount($amount))
        : $pricing->formatAmount($amount);
@endphp
@if ($priceState === 'missing')
    <span>قیمت نیازمند استعلام</span>
@else
    @if (in_array($priceState, ['expired', 'unverified'], true))
        <span>آخرین قیمت ثبت‌شده: </span>
    @endif
    <span>{{ $displayPrice($pricing->exactPrice($product)) }} تومان</span>
    @if ($product->hasSalePrice() && ! in_array($priceState, ['expired', 'unverified'], true))
        <del class="product-price__regular">{{ $displayPrice($pricing->amount($product->price)) }} تومان</del>
    @endif
@endif
