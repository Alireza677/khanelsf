@php
    $data = app(\App\CMS\Blocks\Product\ProductSummaryBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $product = $context['product'] ?? null;
    $preview = ! empty($isPreview) || ! empty($context['isPreview']);
    $gallery = $context['galleryPresentation'] ?? [];
    $availability = $context['availability'] ?? [];
    $category = $context['displayCategory'] ?? null;
    $uid = 'product-summary-'.($data['block_id'] ?: ($product?->id ?? 'empty'));
    $highlights = collect($context['globalAttributes'] ?? [])->whereIn('id', $data['settings']['highlight_attribute_ids']);
@endphp
@if ($product instanceof \App\Models\Product)
    <section class="product-summary" dir="rtl" aria-labelledby="{{ $uid }}-title" data-product-summary>
        <nav class="product-summary__breadcrumbs" aria-label="مسیر صفحه">
            <ol>
                @foreach ($context['breadcrumbs'] ?? [] as $crumb)
                    <li>@if ($crumb['url'])<a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>@else<span aria-current="page">{{ $crumb['label'] }}</span>@endif</li>
                @endforeach
            </ol>
        </nav>
        <div class="product-summary__grid">
            <div class="product-summary__gallery" data-product-gallery>
                @if ($gallery)
                    <a class="product-summary__main-image" href="{{ $gallery[0]['url'] }}"
                       data-gallery-lightbox-src="{{ $gallery[0]['url'] }}" data-gallery-lightbox-alt="{{ $gallery[0]['alt'] ?: ($gallery[0]['name'] ?: $product->title) }}"
                       data-gallery-lightbox-group="{{ $uid }}" data-product-main-image aria-label="بزرگ‌نمایی تصویر محصول">
                        <img src="{{ $gallery[0]['url'] }}" alt="{{ $gallery[0]['alt'] ?: ($gallery[0]['name'] ?: $product->title) }}" decoding="async" fetchpriority="high">
                        <span class="product-summary__expand" aria-hidden="true"><x-heroicon-o-arrows-pointing-out /></span>
                    </a>
                    <p class="product-summary__image-error" data-product-image-error hidden role="status">تصویر بارگیری نشد؛ تصویر دیگری را انتخاب کنید.</p>
                    @if (count($gallery) > 1)
                        <div class="product-summary__thumbnails" aria-label="تصاویر محصول">
                            @foreach ($gallery as $image)
                                <a href="{{ $image['url'] }}" data-product-thumbnail data-image-alt="{{ $image['alt'] ?: ($image['name'] ?: $product->title) }}"
                                   aria-label="نمایش تصویر {{ \App\Support\PersianDate::digits($loop->iteration) }}" @if ($loop->first) aria-current="true" @endif>
                                    <img src="{{ $image['url'] }}" alt="" loading="lazy" decoding="async">
                                </a>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="product-summary__placeholder" role="img" aria-label="تصویر محصول موجود نیست">
                        <x-heroicon-o-photo aria-hidden="true" /><span>تصویر محصول موجود نیست</span>
                    </div>
                @endif
            </div>
            <div class="product-summary__information">
                <h1 id="{{ $uid }}-title">{{ $product->title }}</h1>
                @if (filled($product->excerpt))<p class="product-summary__excerpt">{{ $product->excerpt }}</p>@endif
                <dl class="product-summary__meta">
                    @if (filled($product->sku))<div><dt>شناسه محصول</dt><dd><bdi dir="auto">{{ $product->sku }}</bdi></dd></div>@endif
                    @if ($context['brand'] ?? null)<div><dt>برند</dt><dd>{{ $context['brand']['name'] }}</dd></div>@endif
                    @if ($category)<div><dt>دسته‌بندی</dt><dd><a href="{{ route('shop.category', $category->slug) }}">{{ $category->name }}</a></dd></div>@endif
                </dl>
                @if ($highlights->isNotEmpty())
                    <dl class="product-summary__highlights">@foreach ($highlights as $attribute)
                        <div><dt>{{ $attribute['name'] }}</dt><dd>{{ implode('، ', $attribute['values']) }} @if ($attribute['unit'])<span>{{ $attribute['unit'] }}</span>@endif</dd></div>
                    @endforeach</dl>
                @endif
                <p class="product-summary__stock">@if (($availability['stockStatus'] ?? '') === 'in_stock')<x-heroicon-o-check-circle aria-hidden="true" />موجود@else<x-heroicon-o-x-circle aria-hidden="true" />ناموجود@endif</p>
                <div class="product-summary__price">
                    <span>قیمت محصول</span>
                    <div>@include('shop.partials.price', ['product' => $product, 'pricePresentation' => $availability['priceFreshness'], 'persianPriceDigits' => true])</div>
                </div>
                <div class="product-summary__notice">@include('shop.partials.price-notice', ['product' => $product, 'pricePresentation' => $availability['priceFreshness']])</div>
                @if (($errors ?? null)?->has('quantity') || ($errors ?? null)?->has('cart'))
                    <div role="alert" class="product-summary__error">{{ $errors->first('quantity') ?: $errors->first('cart') }}</div>
                @endif
                @if ($preview)
                    <p class="product-summary__preview" role="note">پیش‌نمایش مدیر؛ خرید و تغییر علاقه‌مندی غیرفعال است.</p>
                @elseif ($availability['purchasable'] ?? false)
                    <form class="product-summary__purchase" method="post" action="{{ route('cart.add', $product) }}">
                        @csrf
                        <label for="{{ $uid }}-quantity">تعداد</label>
                        <div class="product-summary__quantity" data-product-quantity>
                            <button type="button" data-quantity-decrease aria-label="کاهش تعداد" hidden>−</button>
                            <input id="{{ $uid }}-quantity" name="quantity" type="number" min="1" max="99" step="1" inputmode="numeric" value="{{ old('quantity', 1) }}" required aria-describedby="{{ $uid }}-quantity-hint">
                            <button type="button" data-quantity-increase aria-label="افزایش تعداد" hidden>+</button>
                        </div>
                        <button class="button product-summary__add" type="submit"><x-heroicon-o-shopping-cart aria-hidden="true" />افزودن به سبد خرید</button>
                        <small id="{{ $uid }}-quantity-hint">تعداد صحیح از ۱ تا ۹۹</small>
                    </form>
                @else
                    <p class="product-summary__unavailable">این محصول در حال حاضر قابل خرید نیست.</p>
                @endif
                <div class="product-summary__actions">
                    @if ($data['settings']['show_favorites'])
                        @if ($preview)
                            <button type="button" class="product-summary__action" disabled>علاقه‌مندی در پیش‌نمایش غیرفعال است</button>
                        @else
                            <form method="post" action="{{ route('shop.favorites.toggle', $product) }}">@csrf
                                <button class="product-summary__action" type="submit" aria-pressed="{{ ! empty($context['isFavorite']) ? 'true' : 'false' }}" aria-label="{{ ! empty($context['isFavorite']) ? 'حذف از علاقه‌مندی‌های این مرورگر' : 'افزودن به علاقه‌مندی‌های این مرورگر' }}">
                                    <x-heroicon-o-heart aria-hidden="true" />{{ ! empty($context['isFavorite']) ? 'در علاقه‌مندی‌ها' : 'علاقه‌مندی' }}
                                </button>
                            </form>
                        @endif
                    @endif
                    @if ($data['settings']['show_share'])
                        <button type="button" class="product-summary__action" data-product-share data-share-url="{{ route('shop.show', $product->slug) }}" data-share-title="{{ $product->title }}" hidden><x-heroicon-o-share aria-hidden="true" />اشتراک‌گذاری</button>
                    @endif
                </div>
                <p class="product-summary__share-status" role="status" aria-live="polite" data-product-share-status></p>
                <div data-product-share-fallback hidden><label for="{{ $uid }}-share">آدرس محصول؛ برای کپی انتخاب کنید</label><input id="{{ $uid }}-share" type="url" value="{{ route('shop.show', $product->slug) }}" readonly dir="ltr"></div>
            </div>
        </div>
    </section>
@endif
