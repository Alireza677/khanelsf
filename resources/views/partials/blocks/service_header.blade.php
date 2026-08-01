@php
    $data = app(\App\CMS\Blocks\Service\ServiceHeaderBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $content = is_array($context['content'] ?? null) ? $context['content'] : [];
    $featured = data_get($context, 'media.featured');
    $settings = $data['settings'];
    $name = is_scalar($content['name'] ?? null) ? trim((string) $content['name']) : '';
    $excerpt = is_scalar($content['excerpt'] ?? null) ? trim((string) $content['excerpt']) : '';
    $icon = is_scalar($content['icon'] ?? null) ? trim((string) $content['icon']) : '';
@endphp

@if ($name !== '')
    <header
        class="content-block service-header service-header--{{ $settings['variant'] }} service-header--align-{{ $settings['alignment'] }}"
        dir="rtl"
    >
        <div class="service-header__content">
            @if ($icon !== '')
                <span class="service-header__icon" aria-hidden="true">{{ $icon }}</span>
            @endif

            @include('partials.blocks._heading', ['title' => $name, 'tag' => data_get($data, 'settings.heading_tag', 'h1')])

            @if ($settings['show_excerpt'] && $excerpt !== '')
                <p class="service-header__excerpt">{{ $excerpt }}</p>
            @endif
        </div>

        @if ($settings['show_image'] && filled(data_get($featured, 'url')))
            <figure class="service-header__media">
                <img
                    src="{{ data_get($featured, 'url') }}"
                    alt="{{ data_get($featured, 'name') ?: $name }}"
                >
            </figure>
        @endif
    </header>
@endif
