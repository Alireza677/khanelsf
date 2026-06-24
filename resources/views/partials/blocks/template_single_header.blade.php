@php
    $model = $context['model'] ?? null;
    $title = $data['title'] ?? $model?->title;
    $excerpt = $data['description'] ?? $model?->excerpt;
    $image = $model && method_exists($model, 'featuredImageUrl') ? $model->featuredImageUrl() : null;
@endphp

@if ($model && $title)
    <section class="content-block">
        <article class="project-detail">
            <header>
                @if (! empty($data['eyebrow']))
                    <span class="eyebrow">{{ $data['eyebrow'] }}</span>
                @endif

                <h1>{{ $title }}</h1>

                @if ($excerpt)
                    <p>{{ $excerpt }}</p>
                @endif
            </header>

            @if ($image)
                <img class="project-detail__image" src="{{ $image }}" alt="{{ $title }}">
            @endif
        </article>
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Template Single Header needs a single item context.</p>
@endif
