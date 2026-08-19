@php
    $heading = $data['title'] ?? $context['heading'] ?? $context['category']?->name ?? $context['category']?->title ?? null;
    $description = $data['description'] ?? $context['description'] ?? $context['category']?->description ?? null;
    $eyebrow = $data['eyebrow'] ?? $context['eyebrow'] ?? null;
    $variant = in_array($data['variant'] ?? null, ['default', 'modern'], true) ? $data['variant'] : 'default';
    $alignment = in_array($data['alignment'] ?? null, ['start', 'center'], true) ? $data['alignment'] : 'start';
    $spacing = in_array($data['spacing'] ?? null, ['compact', 'comfortable'], true) ? $data['spacing'] : 'comfortable';
    $backgroundImage = $data['background_image'] ?? null;
    $backgroundType = in_array($data['background_type'] ?? null, ['default', 'solid', 'gradient', 'image'], true)
        ? $data['background_type']
        : (filled($backgroundImage) ? 'image' : 'default');
    $safeColor = fn (mixed $value, string $fallback): string => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
        ? $value
        : $fallback;
    $backgroundColor = $safeColor($data['background_color'] ?? null, '#f5f8ff');
    $gradientFrom = $safeColor($data['gradient_from'] ?? null, '#f5f8ff');
    $gradientTo = $safeColor($data['gradient_to'] ?? null, '#eef4ff');
    $overlayOpacity = (int) ($data['overlay_opacity'] ?? 45);
    $overlayOpacity = max(0, min(90, $overlayOpacity));
    $overlay = number_format($overlayOpacity / 100, 2, '.', '');
    $style = match ($backgroundType) {
        'solid' => "background-color: {$backgroundColor};",
        'gradient' => "background-image: linear-gradient(135deg, {$gradientFrom}, {$gradientTo});",
        'image' => filled($backgroundImage)
            ? "background-image: linear-gradient(rgba(15, 23, 42, {$overlay}), rgba(15, 23, 42, {$overlay})), url('".e($backgroundImage)."');"
            : null,
        default => null,
    };
    $type = $context['type'] ?? null;
    $indexCrumbs = [
        'posts' => ['label' => 'وبلاگ', 'route' => 'blog.index'],
        'projects' => ['label' => 'پروژه‌ها', 'route' => 'projects.index'],
        'products' => ['label' => 'فروشگاه', 'route' => 'shop.index'],
        'galleries' => ['label' => 'گالری‌ها', 'route' => 'galleries.index'],
        'services' => ['label' => 'خدمات', 'route' => 'services.index'],
    ];
    $category = $context['category'] ?? $context['activeCategory'] ?? null;
    $breadcrumbs = [
        ['label' => 'خانه', 'url' => route('home')],
    ];

    if (isset($indexCrumbs[$type])) {
        $breadcrumbs[] = [
            'label' => $indexCrumbs[$type]['label'],
            'url' => $category ? route($indexCrumbs[$type]['route']) : null,
        ];
    }

    if ($category || (! isset($indexCrumbs[$type]) && $heading)) {
        $breadcrumbs[] = [
            'label' => $category?->name ?? $category?->title ?? $heading,
            'url' => null,
        ];
    }
@endphp

@if ($heading || $description)
    <section @class([
        'content-block',
        'archive-header',
        "archive-header--{$variant}",
        "archive-header--align-{$alignment}",
        "archive-header--spacing-{$spacing}",
        'archive-header--image' => $backgroundType === 'image' && filled($backgroundImage),
    ]) @if ($style) style="{!! $style !!}" @endif>
        <div class="archive-header__inner">
            @if (count($breadcrumbs) > 1)
                <nav class="archive-breadcrumb" aria-label="مسیر صفحه">
                    <ol>
                        @foreach ($breadcrumbs as $breadcrumb)
                            <li>
                                @if (! empty($breadcrumb['url']))
                                    <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                                @else
                                    <span aria-current="page">{{ $breadcrumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="archive-header__content">
                @if (filled($eyebrow))
                    <p class="block-eyebrow">{{ $eyebrow }}</p>
                @endif

                @if ($heading)
                    @include('partials.blocks._heading', ['title' => $heading, 'tag' => $data['heading_tag'] ?? 'h1'])
                @endif

                @if ($description)
                    <p>{{ $description }}</p>
                @endif
            </div>
        </div>
    </section>
@endif
