@php
    $content = $hero['content'];
    $settings = $hero['settings'];
    $media = $content['media'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $alignment = $settings['alignment'] === 'left' ? 'left' : 'right';
    $stats = collect($content['stats'])->filter(fn ($item) => filled($item['value'] ?? null) || filled($item['label'] ?? null))->values();
@endphp

@include('partials.blocks._image_control_styles')

<section class="content-block hero-template-3 hero-template-3--{{ $alignment }}">
    <div class="hero-template-3__inner">
        <div class="hero-template-3__media">
            @if (! empty($media['url']))
                <img class="block-configured-image" src="{{ $media['url'] }}" alt="{{ $content['title'] ?? '' }}" style="{{ \App\Support\BlockImageStyle::normalizedImageVariables($settings['media']) }}">
            @endif
        </div>

        <div class="hero-template-3__content">
            @if (! empty($content['eyebrow']['text']))
                <p class="hero-template-3__eyebrow">{{ $content['eyebrow']['text'] }}</p>
            @endif
            @if (! empty($content['title']))
                @include('partials.blocks._heading', ['title' => $content['title'], 'tag' => $settings['heading_tag']])
            @endif
            @if (! empty($content['lead']))
                <p class="hero-template-3__description">{{ $content['lead'] }}</p>
            @elseif (! empty($content['description']))
                <p class="hero-template-3__description">{{ $content['description'] }}</p>
            @endif
            @if ((! empty($primaryCta['label']) && ! empty($primaryCta['url'])) || (! empty($secondaryCta['label']) && ! empty($secondaryCta['url'])))
                <div class="hero-template-3__actions">
                    @if (! empty($primaryCta['label']) && ! empty($primaryCta['url']))<a class="button hero-template-3__primary" href="{{ $primaryCta['url'] }}">{{ $primaryCta['label'] }}</a>@endif
                    @if (! empty($secondaryCta['label']) && ! empty($secondaryCta['url']))<a class="button hero-template-3__secondary" href="{{ $secondaryCta['url'] }}">{{ $secondaryCta['label'] }}</a>@endif
                </div>
            @endif
            @if ($stats->isNotEmpty())
                <div class="hero-template-3__stats">
                    @foreach ($stats as $stat)
                        <div class="hero-template-3__stat">
                            @if (! empty($stat['icon']))<span class="hero-template-3__stat-icon">@include('partials.blocks._icon', ['icon' => $stat['icon'], 'size' => $stat['icon_size'] ?? null])</span>@endif
                            @if (! empty($stat['value']))<strong>{{ $stat['value'] }}</strong>@endif
                            @if (! empty($stat['label']))<span>{{ $stat['label'] }}</span>@endif
                            @if (! empty($stat['description']))<small>{{ $stat['description'] }}</small>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
