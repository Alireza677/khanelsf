@php
    $settings = app(\App\Services\SettingsService::class);
    $menus = app(\App\Services\MenuService::class);

    $siteName = $settings->siteName();
    $logoUrl = $settings->logoUrl();
    $mainMenu = $menus->main();
    $ctaLabel = $settings->headerCtaLabel();
    $ctaUrl = $settings->headerCtaUrl();
@endphp

<header class="site-header" data-site-header>
    <div class="container">
        <div class="header-main">
            <a class="site-brand" href="{{ route('home') }}" aria-label="{{ $siteName }}">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}">
                @else
                    <span class="brand-title">{{ $siteName }}</span>
                @endif
            </a>

            <button class="menu-toggle" type="button" aria-controls="site-navigation" aria-expanded="false" data-menu-toggle>
                <span class="menu-toggle__bar"></span>
                <span class="menu-toggle__bar"></span>
                <span class="menu-toggle__bar"></span>
                <span class="sr-only">باز کردن منو</span>
            </button>

            <nav class="site-nav" id="site-navigation" aria-label="ناوبری اصلی" data-site-nav>
                @if ($mainMenu?->rootItems?->isNotEmpty())
                    <ul>
                        @foreach ($mainMenu->rootItems as $item)
                            <li class="{{ $item->children->isNotEmpty() ? 'has-children' : '' }}">
                                <a
                                    href="{{ $item->url ?: '#' }}"
                                    target="{{ $item->target }}"
                                    @if ($item->target === '_blank') rel="noopener noreferrer" @endif
                                >
                                    {{ $item->title }}
                                </a>

                                @if ($item->children->isNotEmpty())
                                    <ul>
                                        @foreach ($item->children as $child)
                                            <li>
                                                <a
                                                    href="{{ $child->url ?: '#' }}"
                                                    target="{{ $child->target }}"
                                                    @if ($child->target === '_blank') rel="noopener noreferrer" @endif
                                                >
                                                    {{ $child->title }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <ul>
                        <li><a href="{{ route('home') }}">خانه</a></li>
                        <li><a href="{{ route('blog.index') }}">وبلاگ</a></li>
                        <li><a href="{{ route('contact.create') }}">تماس</a></li>
                    </ul>
                @endif
            </nav>

            @if ($ctaLabel && $ctaUrl)
                <div class="header-actions">
                    <a class="header-cta" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>
                </div>
            @endif
        </div>
    </div>
</header>
