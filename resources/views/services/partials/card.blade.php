@php
    $url = $service->resolveNavigationUrl();
    $image = $service->getFirstMediaUrl('featured_image', 'thumb');
@endphp

<article class="blog-card service-archive-card">
    <a class="blog-card__image" href="{{ $url }}" aria-label="{{ $service->name }}">
        @if (filled($image))
            <img src="{{ $image }}" alt="{{ $service->name }}">
        @elseif (filled($service->icon))
            <span class="service-archive-card__icon" aria-hidden="true">{{ $service->icon }}</span>
        @else
            <span>{{ $service->name }}</span>
        @endif
    </a>

    <div class="blog-card__body">
        <h2><a href="{{ $url }}">{{ $service->name }}</a></h2>

        @if (filled($service->excerpt))
            <p>{{ $service->excerpt }}</p>
        @endif
    </div>
</article>
