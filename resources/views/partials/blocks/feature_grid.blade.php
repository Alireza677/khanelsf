@php
    $content = $grid['content'];
    $settings = $grid['settings'];
    $items = $grid['items'];
@endphp

@include('partials.blocks._image_control_styles')

<section data-feature-grid-section @class([
    'content-block',
    'block-feature-grid',
    'block-feature-grid--icon-list' => $settings['variant'] === 'icon_list',
    "content-block--{$settings['section_background']}" => $settings['section_background'] !== 'default',
])>
    <div class="block-feature-grid__inner">
        <div class="block-heading">
            @if (! empty($settings['eyebrow']))
                <p class="block-eyebrow">{{ $settings['eyebrow'] }}</p>
            @endif

            @if (! empty($content['section_title']))
                @include('partials.blocks._heading', ['title' => $content['section_title'], 'tag' => $settings['heading_tag']])
            @endif

            @include('partials.blocks._rich_text', ['content' => $content['section_description'] ?? null])
        </div>

        <div @class(['block-grid', 'block-grid--dynamic' => $grid['dynamic']]) @if ($grid['grid_style']) style="{{ $grid['grid_style'] }} --feature-grid-item-count: {{ count($items) }};" @endif>
            @foreach ($items as $item)
                <article class="block-card">
                    @php($hasMedia = ! empty($item['image']) || ! empty($item['icon']))
                    @if ($settings['variant'] === 'icon_list' && $hasMedia)<div class="block-card__media">@endif
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
                    @if ($settings['variant'] === 'icon_list' && $hasMedia)</div>@endif

                    @if ($settings['variant'] === 'icon_list')<div class="block-card__content">@endif

                    @if (! empty($item['title']))
                        <h3>{{ $item['title'] }}</h3>
                    @endif

                    @include('partials.blocks._rich_text', [
                        'content' => $item['description'] ?? null,
                        'class' => 'block-card__description',
                    ])

                    @include('partials.actions.render', [
                        'label' => $item['button_label'] ?? null,
                        'class' => 'button block-card__button',
                        'presentation' => $item['presentation'] ?? null,
                    ])
                    @if ($settings['variant'] === 'icon_list')</div>@endif
                </article>
            @endforeach
        </div>
    </div>
</section>

@include('partials.blocks._feature_grid_full_bleed')
