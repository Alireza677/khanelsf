@php
    $content = $textImage['content'];
    $settings = $textImage['settings'];
    $image = $textImage['image'];
@endphp

@if ($image || $content['title'] || $content['eyebrow'] || $content['description'] || $textImage['presentation'])
    <section @class([
        'content-block block-text-image',
        "content-block--{$settings['section_background']}" => $settings['section_background'] !== 'default',
        "block-text-image--image-{$settings['image_position']}",
        "block-text-image--{$settings['column_ratio']}",
        "block-text-image--align-{$settings['vertical_alignment']}",
        'block-text-image--without-image' => ! $image,
    ])>
        <div class="block-text-image__inner">
            @if ($image)
                <div class="block-text-image__media" @if ($settings['image_ratio'] !== 'natural') style="aspect-ratio: {{ str_replace(':', ' / ', $settings['image_ratio']) }}" @endif>
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async">
                </div>
            @endif

            <div class="block-text-image__content" dir="rtl">
                @if ($content['eyebrow'])
                    <p class="block-eyebrow">{{ $content['eyebrow'] }}</p>
                @endif
                @if ($content['title'])
                    <{{ $settings['heading_tag'] }} class="block-title">{{ $content['title'] }}</{{ $settings['heading_tag'] }}>
                @endif

                @include('partials.blocks._rich_text', ['content' => $content['description']])

                @if ($textImage['presentation'])
                    <div class="block-actions">
                        @include('partials.actions.render', [
                            'label' => $content['button']['label'],
                            'class' => 'button',
                            'presentation' => $textImage['presentation'],
                        ])
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
