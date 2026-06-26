@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'left') === 'center' ? 'center' : 'left';
@endphp

@include('partials.blocks._image_control_styles')

<section @class([
    'content-block',
    'block-hero',
    "content-block--{$background}" => $background !== 'default',
    "content-block--align-{$alignment}",
])>
    <div class="block-hero__content">
        @if (! empty($data['subtitle']))
            <p class="block-eyebrow">{{ $data['subtitle'] }}</p>
        @endif

        @if (! empty($data['title']))
            @include('partials.blocks._heading', ['title' => $data['title'], 'tag' => $data['heading_tag'] ?? 'h2'])
        @endif

        @if (! empty($data['description']))
            <p>{{ $data['description'] }}</p>
        @endif

        @if ((! empty($data['primary_button_label']) && ! empty($data['primary_button_url'])) || (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url'])))
            <div class="block-actions">
                @if (! empty($data['primary_button_label']) && ! empty($data['primary_button_url']))
                    <a class="button" href="{{ $data['primary_button_url'] }}">{{ $data['primary_button_label'] }}</a>
                @endif

                @if (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url']))
                    <a class="button button-secondary" href="{{ $data['secondary_button_url'] }}">{{ $data['secondary_button_label'] }}</a>
                @endif
            </div>
        @endif
    </div>

    @if (! empty($data['image']))
        <img
            class="block-hero__image block-configured-image"
            src="{{ $data['image'] }}"
            alt="{{ $data['title'] ?? '' }}"
            style="{{ \App\Support\BlockImageStyle::imageVariables($data, 'image') }}"
        >
    @endif
</section>
