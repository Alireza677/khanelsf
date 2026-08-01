@php
    $heading = $data['title'] ?? $context['heading'] ?? $context['category']?->name ?? $context['category']?->title ?? null;
    $description = $data['description'] ?? $context['description'] ?? $context['category']?->description ?? null;
    $backgroundImage = $data['background_image'] ?? null;
    $overlayOpacity = (int) ($data['overlay_opacity'] ?? 45);
    $overlayOpacity = max(0, min(90, $overlayOpacity));
    $overlay = number_format($overlayOpacity / 100, 2, '.', '');
    $style = filled($backgroundImage)
        ? "background-image: linear-gradient(rgba(15, 23, 42, {$overlay}), rgba(15, 23, 42, {$overlay})), url('".e($backgroundImage)."');"
        : null;
    $type = $context['type'] ?? null;
    $indexCrumbs = [
        'posts' => ['label' => 'وبلاگ', 'route' => 'blog.index'],
        'projects' => ['label' => 'پروژه‌ها', 'route' => 'projects.index'],
        'products' => ['label' => 'فروشگاه', 'route' => 'shop.index'],
        'galleries' => ['label' => 'گالری‌ها', 'route' => 'galleries.index'],
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
    <section class="content-block archive-header @if ($backgroundImage) archive-header--image @endif" @if ($style) style="{!! $style !!}" @endif>
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
                @if (! empty($data['eyebrow']))
                    <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
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
