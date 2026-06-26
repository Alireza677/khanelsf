@php
    $alignment = ($data['hero_3_alignment'] ?? 'right') === 'left' ? 'left' : 'right';
    $stats = collect($data['stats'] ?? [])
        ->filter(fn ($item) => filled($item['value'] ?? null) || filled($item['label'] ?? null))
        ->values();
@endphp

@include('partials.blocks._image_control_styles')

<section class="content-block hero-template-3 hero-template-3--{{ $alignment }}">
    <div class="hero-template-3__inner">
        <div class="hero-template-3__media">
            @if (! empty($data['image']))
                <img
                    class="block-configured-image"
                    src="{{ $data['image'] }}"
                    alt="{{ $data['title'] ?? '' }}"
                    style="{{ \App\Support\BlockImageStyle::imageVariables($data, 'image') }}"
                >
            @endif
        </div>

        <div class="hero-template-3__content">
            @if (! empty($data['eyebrow']))
                <p class="hero-template-3__eyebrow">{{ $data['eyebrow'] }}</p>
            @endif

            @if (! empty($data['title']))
                <h1>{{ $data['title'] }}</h1>
            @endif

            @if (! empty($data['subtitle']))
                <p class="hero-template-3__description">{{ $data['subtitle'] }}</p>
            @elseif (! empty($data['description']))
                <p class="hero-template-3__description">{{ $data['description'] }}</p>
            @endif

            @if ((! empty($data['primary_button_label']) && ! empty($data['primary_button_url'])) || (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url'])))
                <div class="hero-template-3__actions">
                    @if (! empty($data['primary_button_label']) && ! empty($data['primary_button_url']))
                        <a class="button hero-template-3__primary" href="{{ $data['primary_button_url'] }}">{{ $data['primary_button_label'] }}</a>
                    @endif

                    @if (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url']))
                        <a class="button hero-template-3__secondary" href="{{ $data['secondary_button_url'] }}">{{ $data['secondary_button_label'] }}</a>
                    @endif
                </div>
            @endif

            @if ($stats->isNotEmpty())
                <div class="hero-template-3__stats">
                    @foreach ($stats as $stat)
                        <div class="hero-template-3__stat">
                            @if (! empty($stat['icon']))
                                <span class="hero-template-3__stat-icon">
                                    @include('partials.blocks._icon', ['icon' => $stat['icon'], 'size' => $stat['icon_size'] ?? null])
                                </span>
                            @endif
                            @if (! empty($stat['value']))
                                <strong>{{ $stat['value'] }}</strong>
                            @endif
                            @if (! empty($stat['label']))
                                <span>{{ $stat['label'] }}</span>
                            @endif
                            @if (! empty($stat['description']))
                                <small>{{ $stat['description'] }}</small>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
