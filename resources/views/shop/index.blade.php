@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <section class="blog-index">
        <header class="blog-index__header">
            <h1>{{ $heading ?? 'فروشگاه' }}</h1>

            @if (! empty($description))
                <p>{{ $description }}</p>
            @endif
        </header>

        @if (($categories ?? collect())->isNotEmpty())
            <nav class="archive-nav" aria-label="دسته‌بندی محصولات">
                <a href="{{ route('shop.index') }}" @class(['is-active' => empty($activeCategory)])>همه</a>

                @foreach ($categories as $category)
                    <a href="{{ route('shop.category', $category->slug) }}" @class(['is-active' => isset($activeCategory) && $activeCategory->is($category)])>
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="blog-index__grid">
            @forelse ($products as $product)
                @include('shop.partials.card', ['product' => $product])
            @empty
                <p class="blog-index__empty">{{ $emptyMessage ?? 'هنوز محصولی منتشر نشده است.' }}</p>
            @endforelse
        </div>

        <div class="blog-index__pagination">
            {{ $products->links() }}
        </div>
    </section>
@endsection
