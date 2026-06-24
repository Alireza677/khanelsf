@php
    $product = ($context['type'] ?? null) === 'product' ? ($context['model'] ?? null) : null;
@endphp

@if ($product)
    <section class="content-block">
        @if ($product->isPurchasable() && empty($isPreview))
            <form class="cart-inline-form" method="post" action="{{ route('cart.add', $product) }}">
                @csrf
                <label for="template-quantity-{{ $product->id }}">تعداد</label>
                <input id="template-quantity-{{ $product->id }}" name="quantity" type="number" min="1" max="99" value="1">
                <button class="button" type="submit">{{ $data['button_label'] ?? 'افزودن به سبد خرید' }}</button>
            </form>
        @elseif (! empty($isPreview))
            <p class="empty-state">این صفحه پیش‌نمایش مدیر است و افزودن به سبد خرید غیرفعال است.</p>
        @else
            <p class="empty-state">این محصول در حال حاضر قابل خرید نیست.</p>
        @endif
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">بلاک افزودن به سبد خرید فقط در قالب محصول کار می‌کند.</p>
@endif
