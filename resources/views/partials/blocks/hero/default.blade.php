@php
    $content = $hero['content'];
    $settings = $hero['settings'];
    $media = $content['media'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $background = in_array($settings['color_mode'], ['muted', 'dark'], true) ? $settings['color_mode'] : 'default';
    $alignment = $settings['alignment'] === 'center' ? 'center' : 'left';
@endphp

@include('partials.blocks._image_control_styles')

<section @class([
    'content-block',
    'block-hero',
    "content-block--{$background}" => $background !== 'default',
    "content-block--align-{$alignment}",
])>
    <div class="block-hero__content">
        @if (! empty($content['lead']))
            <p class="block-eyebrow">{{ $content['lead'] }}</p>
        @endif

        @if (! empty($content['title']))
            @include('partials.blocks._heading', ['title' => $content['title'], 'tag' => $settings['heading_tag']])
        @endif

        @if (! empty($content['description']))
            <p>{{ $content['description'] }}</p>
        @endif

        @if ((! empty($primaryCta['label']) && ! empty($primaryCta['url'])) || (! empty($secondaryCta['label']) && ! empty($secondaryCta['url'])))
            <div class="block-actions">
                @if (! empty($primaryCta['label']) && ! empty($primaryCta['url']))
                    <a class="button" href="{{ $primaryCta['url'] }}">{{ $primaryCta['label'] }}</a>
                @endif

                @if (! empty($secondaryCta['label']) && ! empty($secondaryCta['url']))
                    <a class="button button-secondary" href="{{ $secondaryCta['url'] }}">{{ $secondaryCta['label'] }}</a>
                @endif
            </div>
        @endif
    </div>

    @if (! empty($media['url']))
        <img
            class="block-hero__image block-configured-image"
            src="{{ $media['url'] }}"
            alt="{{ $content['title'] ?? '' }}"
            style="{{ \App\Support\BlockImageStyle::normalizedImageVariables($settings['media']) }}"
        >
    @endif
</section>
