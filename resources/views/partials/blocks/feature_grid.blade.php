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

        @if (! empty($grid['notice'])) <p role="status">{{ $grid['notice'] }}</p> @endif
        @include('partials.blocks.feature-grid.cards', ['grid' => $grid])
    </div>
</section>

@include('partials.blocks._feature_grid_full_bleed')
