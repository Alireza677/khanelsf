@php
    $template = $data['cta_template'] ?? 'classic';
    $requestedBackground = $data['section_background'] ?? 'dark';
    $background = in_array($requestedBackground, ['muted', 'dark'], true) ? $requestedBackground : 'default';
    $alignment = ($data['alignment'] ?? 'left') === 'center' ? 'center' : 'left';
    $backgroundImage = $data['background_image'] ?? null;
    $backgroundVariables = \App\Support\BlockImageStyle::backgroundVariables($data, 'background_image');
    $imageStyle = filled($backgroundImage)
        ? "background-image: linear-gradient(90deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,.58) 42%, rgba(255,255,255,.98) 74%), url('".e($backgroundImage)."');"
        : null;
    $contentWidth = filled($data['content_width'] ?? null)
        ? max(240, min(1400, (int) $data['content_width']))
        : null;
@endphp

@include('partials.blocks._image_control_styles')

@if ($template === 'image')
    <section
        class="content-block block-cta-image block-configured-background"
        @if ($imageStyle || $backgroundVariables) style="{!! trim($imageStyle.' '.$backgroundVariables, ' ;') !!}" @endif
    >
        <div class="block-cta-image__content" @if ($contentWidth) style="max-width: {{ $contentWidth }}px" @endif>
            @if (! empty($data['title']))
                <h2>{{ $data['title'] }}</h2>
            @endif

            @if (! empty($data['description']))
                <p>{{ $data['description'] }}</p>
            @endif

            @if ((! empty($data['button_label']) && ! empty($data['button_url'])) || (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url'])))
                <div class="block-cta-image__actions">
                    @if (! empty($data['button_label']) && ! empty($data['button_url']))
                        <a class="button block-cta-image__primary" href="{{ $data['button_url'] }}">{{ $data['button_label'] }}</a>
                    @endif

                    @if (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url']))
                        <a class="button block-cta-image__secondary" href="{{ $data['secondary_button_url'] }}">{{ $data['secondary_button_label'] }}</a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@else
    <section @class([
        'content-block',
        'block-cta',
        "content-block--{$background}" => $background !== 'default' && $background !== 'dark',
        "content-block--align-{$alignment}",
    ])>
        <div>
            @if (! empty($data['eyebrow']))
                <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
            @endif

            @if (! empty($data['title']))
                <h2>{{ $data['title'] }}</h2>
            @endif

            @if (! empty($data['description']))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>

        @if (! empty($data['button_label']) && ! empty($data['button_url']))
            <a class="button" href="{{ $data['button_url'] }}">{{ $data['button_label'] }}</a>
        @endif
    </section>
@endif
