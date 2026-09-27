@php
    $content = $hero['content'];
    $settings = $hero['settings'];
    $media = $content['media'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $stats = collect($content['stats'])->filter(fn ($item) => filled($item['value'] ?? null) || filled($item['label'] ?? null))->values();
@endphp

<section class="content-block hero-template-3 hero-template-3--right" dir="rtl">
    @if (! empty($media['url']))
        <div class="hero-template-3__visual">
            {{-- Full-height scaling preserves the ratio; small screens center the image behind the content. --}}
            <img src="{{ $media['url'] }}" alt="{{ $media['alt'] ?? $content['title'] ?? '' }}" decoding="async">
        </div>
    @endif

    <div class="container hero-template-3__inner">
        <div class="hero-template-3__content">
            @if (! empty($content['eyebrow']['text']))
                <p class="hero-template-3__eyebrow">
                    @if (! empty($content['eyebrow']['icon']))
                        @include('partials.blocks._icon', ['icon' => $content['eyebrow']['icon'], 'size' => $settings['eyebrow_icon_size'] ?? null])
                    @endif
                    {{ $content['eyebrow']['text'] }}
                </p>
            @endif
            @if (! empty($content['title']))
                @include('partials.blocks._heading', ['title' => $content['title'], 'tag' => $settings['heading_tag']])
            @endif
            @include('partials.blocks._rich_text', [
                'content' => $content['description'] ?? null,
                'class' => 'hero-template-3__description',
            ])
            @if ((! empty($primaryCta['label']) && ! empty($primaryCta['presentation'])) || (! empty($secondaryCta['label']) && ! empty($secondaryCta['presentation'])))
                <div class="hero-template-3__actions">
                    @include('partials.actions.render', ['label' => $primaryCta['label'], 'class' => 'button', 'presentation' => $primaryCta['presentation']])
                    @include('partials.actions.render', ['label' => $secondaryCta['label'], 'class' => 'button hero-template-3__secondary', 'presentation' => $secondaryCta['presentation']])
                </div>
            @endif
            @if ($stats->isNotEmpty())
                <div class="hero-template-3__stats">
                    @foreach ($stats as $stat)
                        <div class="hero-template-3__stat">
                            @if (! empty($stat['icon']))<span class="hero-template-3__stat-icon">@include('partials.blocks._icon', ['icon' => $stat['icon'], 'size' => $stat['icon_size'] ?? null])</span>@endif
                            @if (filled($stat['value'] ?? null))<strong><bdi>{{ $stat['value'] }}</bdi></strong>@endif
                            @if (! empty($stat['label']))<span>{{ $stat['label'] }}</span>@endif
                            @if (! empty($stat['description']))<small>{{ $stat['description'] }}</small>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>

<script>
    (() => {
        const section = document.currentScript.previousElementSibling;
        // The shared parent isn't a query container, and 100vw includes classic scrollbars.
        // Keep the correction local and synchronous rather than altering the page runtime or root CSS.
        const updateWidth = () => section.style.setProperty('--hero-3-viewport-width', `${document.documentElement.clientWidth}px`);
        updateWidth();
        const observer = new ResizeObserver(() => {
            if (!section.isConnected) {
                observer.disconnect();
                return;
            }
            updateWidth();
        });
        observer.observe(document.documentElement);
    })();
</script>
