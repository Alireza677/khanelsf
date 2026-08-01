@extends('layouts.app')

@section('content')
    <section class="services-index" dir="rtl" aria-labelledby="services-index-title">
        <header class="blog-index__header">
            <h1 id="services-index-title">{{ $heading }}</h1>

            @if (filled($description))
                <p>{{ $description }}</p>
            @endif
        </header>

        <div class="blog-index__grid">
            @forelse ($services as $service)
                @include('services.partials.card', ['service' => $service])
            @empty
                <p class="blog-index__empty">هنوز خدمتی منتشر نشده است.</p>
            @endforelse
        </div>

        @if ($services->hasPages())
            <nav class="blog-index__pagination" aria-label="صفحه‌بندی خدمات">
                {{ $services->links() }}
            </nav>
        @endif
    </section>
@endsection
