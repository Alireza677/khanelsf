@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <article class="project-detail product-detail">
        <header>
            <h1>{{ $product->title }}</h1>

            @if ($product->excerpt)
                <p>{{ $product->excerpt }}</p>
            @endif
        </header>

        @if ($product->featuredImageUrl())
            <img class="project-detail__image" src="{{ $product->featuredImageUrl() }}" alt="{{ $product->title }}">
        @endif

        <dl class="project-meta">
            @if ($product->category)
                <div>
                    <dt>دسته‌بندی</dt>
                    <dd><a href="{{ route('shop.category', $product->category->slug) }}">{{ $product->category->name }}</a></dd>
                </div>
            @endif

            @if ($product->sku)
                <div>
                    <dt>شناسه محصول</dt>
                    <dd>{{ $product->sku }}</dd>
                </div>
            @endif

            <div>
                <dt>وضعیت موجودی</dt>
                <dd>{{ $product->isPurchasable() ? 'موجود' : 'ناموجود' }}</dd>
            </div>

            <div>
                <dt>قیمت</dt>
                <dd>
                    @if ($product->hasSalePrice())
                        <span class="product-price__sale">{{ number_format($product->currentPrice()) }} تومان</span>
                        <span class="product-price__regular">{{ number_format((float) $product->price) }} تومان</span>
                    @else
                        {{ number_format($product->currentPrice()) }} تومان
                    @endif
                </dd>
            </div>
        </dl>

        @if ($product->isPurchasable() && empty($isPreview))
            <form class="cart-inline-form" method="post" action="{{ route('cart.add', $product) }}">
                @csrf
                <label for="quantity">تعداد</label>
                <input id="quantity" name="quantity" type="number" min="1" max="99" value="1">
                <button class="button" type="submit">افزودن به سبد خرید</button>
            </form>
        @elseif (! empty($isPreview))
            <p class="empty-state">این صفحه پیش‌نمایش مدیر است و افزودن به سبد خرید غیرفعال است.</p>
        @else
            <p class="empty-state">این محصول در حال حاضر قابل خرید نیست.</p>
        @endif

        @if ($product->content)
            <section class="project-section">
                {!! $product->content !!}
            </section>
        @endif

        <section class="project-section">
            <h2>گالری</h2>

            @if ($product->galleryImages()->isNotEmpty())
                <div class="block-gallery">
                    @foreach ($product->galleryImages() as $image)
                        <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}">
                    @endforeach
                </div>
            @else
                <p class="empty-state">هنوز تصویری به گالری اضافه نشده است.</p>
            @endif
        </section>
    </article>

    @if (($relatedProducts ?? collect())->isNotEmpty())
        <section>
            <div class="section-heading">
                <h2>محصولات مرتبط</h2>
            </div>

            <div class="latest-posts">
                @foreach ($relatedProducts as $relatedProduct)
                    @include('shop.partials.card', ['product' => $relatedProduct])
                @endforeach
            </div>
        </section>
    @endif
@endsection
