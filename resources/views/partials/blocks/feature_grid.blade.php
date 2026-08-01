@php
    $content = $grid['content'];
    $settings = $grid['settings'];
    $items = $grid['items'];
@endphp

@include('partials.blocks._image_control_styles')

<section @class([
    'content-block',
    'block-feature-grid',
    "content-block--{$settings['section_background']}" => $settings['section_background'] !== 'default',
    "content-block--align-{$settings['alignment']}",
])>
    <div class="block-heading">
        @if (! empty($settings['eyebrow']))
            <p class="block-eyebrow">{{ $settings['eyebrow'] }}</p>
        @endif

        @if (! empty($content['section_title']))
            @include('partials.blocks._heading', ['title' => $content['section_title'], 'tag' => $settings['heading_tag']])
        @endif

        @if (! empty($content['section_description']))
            <p>{{ $content['section_description'] }}</p>
        @endif
    </div>

    <div @class(['block-grid', 'block-grid--dynamic' => $grid['dynamic']]) @if ($grid['grid_style']) style="{{ $grid['grid_style'] }}" @endif>
        @foreach ($items as $item)
            <article class="block-card">
                @if (! empty($item['image']))
                    <img
                        @class(['block-configured-image' => ! $grid['dynamic']])
                        src="{{ $item['image'] }}"
                        alt="{{ $item['title'] ?? '' }}"
                        @if (! $grid['dynamic']) style="{{ \App\Support\BlockImageStyle::imageVariables($item, 'image') }}" @endif
                    >
                @elseif (! empty($item['icon']))
                    <div class="block-card__icon">
                        @include('partials.blocks._icon', ['icon' => $item['icon'], 'size' => $item['icon_size'] ?? null])
                    </div>
                @endif

                @if (! empty($item['title']))
                    <h3>{{ $item['title'] }}</h3>
                @endif

                @if (! empty($item['description']))
                    <p>{{ $item['description'] }}</p>
                @endif

                @include('partials.actions.render', [
                    'label' => $item['button_label'] ?? null,
                    'class' => 'button block-card__button',
                    'presentation' => $item['presentation'] ?? null,
                ])
            </article>
        @endforeach
    </div>
</section>
