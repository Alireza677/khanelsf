@php
    $freshness = app(\App\Services\ProductPriceFreshness::class);
    $pricing = app(\App\Services\ProductPricingService::class);
    $pricePresentation = $pricePresentation ?? $freshness->presentation($product, withInquiry: false);
    $priceState = $pricePresentation['status'];
@endphp
@if ($priceState === 'missing')
    <span>قیمت نیازمند استعلام</span>
@else
    @if (in_array($priceState, ['expired', 'unverified'], true))
        <span>آخرین قیمت ثبت‌شده: </span>
    @endif
    <span>{{ $pricing->formatAmount($pricing->exactPrice($product)) }} تومان</span>
    @if ($product->hasSalePrice() && ! in_array($priceState, ['expired', 'unverified'], true))
        <del class="product-price__regular">{{ $pricing->formatAmount($pricing->amount($product->price)) }} تومان</del>
    @endif
@endif
