@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
@endphp

@include('partials.blocks._image_control_styles')

<section @class([
    'content-block',
    "content-block--{$background}" => $background !== 'default',
    "content-block--align-{$alignment}",
])>
    <div class="block-heading">
        @if (! empty($data['eyebrow']))
            <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
        @endif

        @if (! empty($data['section_title']))
            @include('partials.blocks._heading', ['title' => $data['section_title'], 'tag' => $data['heading_tag'] ?? 'h2'])
        @endif
    </div>

    <div class="block-grid">
        @foreach (collect($data['items'] ?? [])->filter() as $item)
            <figure class="block-card block-testimonial">
                @if (! empty($item['avatar']))
                    <img
                        class="block-configured-image"
                        src="{{ $item['avatar'] }}"
                        alt="{{ $item['name'] ?? '' }}"
                        style="{{ \App\Support\BlockImageStyle::imageVariables($item, 'avatar') }}"
                    >
                @endif

                @if (! empty($item['quote']))
                    <blockquote>{{ $item['quote'] }}</blockquote>
                @endif

                <figcaption>
                    @if (! empty($item['name']))
                        <strong>{{ $item['name'] }}</strong>
                    @endif

                    @if (! empty($item['role']))
                        <span>{{ $item['role'] }}</span>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
