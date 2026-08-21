@extends('layouts.app')

@section('content')
    <section class="public-search-results">
        <header>
            <p>جستجوی عمومی</p>
            <h1>نتایج جستجو برای «{{ $query }}»</h1>
        </header>

        @forelse ($results as $type => $items)
            @if ($items->isNotEmpty())
                <section class="public-search-results__group">
                    <h2>{{ $sources[$type] }}</h2>
                    <div class="public-search-results__grid">
                        @foreach ($items as $result)
                            <article class="public-search-result">
                                @if ($result->image)
                                    <a href="{{ $result->url }}" tabindex="-1" aria-hidden="true">
                                        <img src="{{ $result->image }}" alt="">
                                    </a>
                                @endif
                                <div>
                                    <span>{{ $result->typeLabel }}</span>
                                    <h3><a href="{{ $result->url }}">{{ $result->title }}</a></h3>
                                    @if ($result->excerpt)<p>{{ $result->excerpt }}</p>@endif
                                    @if ($result->meta)<small>{{ $result->meta }}</small>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @empty
        @endforelse

        @if (collect($results)->every(fn ($items) => $items->isEmpty()))
            <p class="empty-state">نتیجه‌ای برای عبارت موردنظر پیدا نشد.</p>
        @endif
    </section>
@endsection
