@php
    $content = $hero['content'];
    $settings = $hero['settings'];
    $media = $content['media'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $link = static fn (array $cta): ?array => filled($cta['label'] ?? null) && filled($cta['url'] ?? null) ? [
        'label' => $cta['label'],
        'presentation' => ['kind' => 'link', 'href' => $cta['url'], 'target' => null, 'rel' => null, 'prevent_default' => false],
    ] : null;
    $presentation = [
        'eyebrow' => $content['lead'],
        'icon' => null,
        'title' => $content['title'],
        'heading_tag' => $settings['heading_tag'],
        'description' => $content['description'],
        'image' => filled($media['url']) ? [
            'url' => $media['url'],
            'alt' => $content['title'] ?? '',
            'style' => \App\Support\BlockImageStyle::normalizedImageVariables($settings['media']),
        ] : null,
        'primary_action' => $link($primaryCta),
        'secondary_action' => $link($secondaryCta),
        'meta_items' => [],
        'variant' => 'default',
        'class' => 'block-hero',
        'background' => in_array($settings['color_mode'], ['muted', 'dark'], true) ? $settings['color_mode'] : 'default',
        'alignment' => $settings['alignment'] === 'center' ? 'center' : 'start',
        'image_position' => 'end',
    ];
@endphp

@include('partials.blocks._image_control_styles')
@include('partials.presentations.hero', ['hero' => $presentation])
