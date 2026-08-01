@php
    $data = app(\App\CMS\Blocks\Product\ProductHeaderBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $product = ($context['product'] ?? null) instanceof \App\Models\Product ? $context['product'] : null;
    $category = $context['category'] ?? null;
    $featured = data_get($context, 'media.featured');
    $effectivePrice = $context['effectivePrice'] ?? null;
    $currency = $context['currency'] ?? 'IRT';
    $availability = is_array($context['availability'] ?? null) ? $context['availability'] : [];
    $settings = $data['settings'];
@endphp

@if ($product)
    <section class="content-block product-header" dir="rtl">
        <div class="product-header__content">
            @if ($data['content']['eyebrow'])
                <p class="block-eyebrow">{{ $data['content']['eyebrow'] }}</p>
            @endif

            @include('partials.blocks._heading', ['title' => $product->title, 'tag' => data_get($data, 'settings.heading_tag', 'h1')])

            @if ($product->excerpt)
                <p class="product-header__intro">{{ $product->excerpt }}</p>
            @endif

            <dl class="product-meta product-header__meta">
                @if ($settings['show_category'] && filled($category?->name))
                    <div>
                        <dt>دسته‌بندی</dt>
                        <dd>
                            <a href="{{ route('shop.category', $category->slug) }}">{{ $category->name }}</a>
                        </dd>
                    </div>
                @endif

                @if ($settings['show_price'] && is_numeric($effectivePrice))
                    <div>
                        <dt>قیمت</dt>
                        <dd>{{ number_format((float) $effectivePrice) }} تومان <small>({{ $currency }})</small></dd>
                    </div>
                @endif

                @if ($settings['show_availability'] && array_key_exists('purchasable', $availability))
                    <div>
                        <dt>وضعیت</dt>
                        <dd>{{ $availability['purchasable'] ? 'موجود و قابل سفارش' : 'ناموجود' }}</dd>
                    </div>
                @endif
            </dl>

            @if ($settings['show_cta'] && ($availability['purchasable'] ?? false))
                @if (empty($context['isPreview']))
                    <form class="cart-inline-form" method="post" action="{{ route('cart.add', $product) }}">
                        @csrf
                        <label for="product-header-quantity-{{ $data['block_id'] ?: 'default' }}">تعداد</label>
                        <input
                            id="product-header-quantity-{{ $data['block_id'] ?: 'default' }}"
                            name="quantity"
                            type="number"
                            min="1"
                            max="99"
                            value="1"
                        >
                        <button class="button" type="submit">افزودن به سبد خرید</button>
                    </form>
                @else
                    <p class="empty-state">افزودن به سبد خرید در پیش‌نمایش غیرفعال است.</p>
                @endif
            @endif
        </div>

        @if ($settings['show_image'] && filled(data_get($featured, 'url')))
            <div class="product-header__media">
                <img
                    class="product-detail__image"
                    src="{{ data_get($featured, 'url') }}"
                    alt="{{ data_get($featured, 'name') ?: $product->title }}"
                >
            </div>
        @endif
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Product Header requires a product context.</p>
@endif
