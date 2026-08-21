<header
    @class([
        'site-header',
        'industrial-header',
        'industrial-header--static' => ! $header['settings']['sticky_enabled'],
    ])
    data-site-header
>
    <div class="industrial-header__main">
        <div @class([
            'container',
            'industrial-header__main-inner',
            'industrial-header__main-inner--with-top-actions' => $header['settings']['top_bar_enabled'] && $header['top_actions'] !== [],
        ])>
            <a
                class="site-brand industrial-header__brand"
                href="{{ $header['home_url'] }}"
                aria-label="{{ $header['site_name'] }}"
            >
                @if ($header['logo_url'])
                    <img src="{{ $header['logo_url'] }}" alt="{{ $header['site_name'] }}">
                @else
                    <span class="brand-title">{{ $header['site_name'] }}</span>
                @endif
            </a>

            <button
                class="menu-toggle industrial-header__toggle"
                type="button"
                aria-controls="{{ $header['navigation_id'] }}"
                aria-expanded="false"
                aria-label="باز کردن منوی اصلی"
                data-menu-toggle
            >
                <span class="menu-toggle__bar"></span>
                <span class="menu-toggle__bar"></span>
                <span class="menu-toggle__bar"></span>
            </button>

            <div
                class="industrial-header__panel"
                id="{{ $header['navigation_id'] }}"
                data-site-nav
            >
                @if ($header['settings']['top_bar_enabled'] && $header['top_actions'] !== [])
                    <div class="industrial-header__top-actions" aria-label="پیوندهای سریع">
                        @foreach ($header['top_actions'] as $action)
                            @include('partials.actions.render', [
                                'label' => $action['label'],
                                'class' => 'industrial-header__top-action',
                                'presentation' => $action['presentation'],
                            ])
                        @endforeach
                    </div>
                @endif

                <nav class="site-nav industrial-header__navigation" aria-label="ناوبری اصلی">
                    @if ($header['navigation'] !== [])
                        <ul data-desktop-navigation>
                            @include('partials.navigation.items', [
                                'items' => $header['navigation'],
                            ])
                            <li class="industrial-header__more" data-navigation-more hidden>
                                <button
                                    class="industrial-header__more-trigger"
                                    type="button"
                                    aria-expanded="false"
                                    data-navigation-more-trigger
                                >
                                    بیشتر
                                </button>
                                <ul data-navigation-more-items></ul>
                            </li>
                        </ul>
                    @endif
                </nav>

                <div @class([
                    'industrial-header__utilities',
                    'industrial-header__utilities--with-cart' => $header['cart'],
                    'industrial-header__utilities--guest-account' => ! $header['account']['authenticated'],
                ])>
                    @if ($header['search_url'])
                        <button
                            type="button"
                            class="industrial-header__icon-action industrial-header__search"
                            aria-label="جستجو در سایت"
                            aria-haspopup="dialog"
                            aria-expanded="false"
                            aria-controls="{{ $header['overlay_ids']['search'] }}"
                            data-header-overlay-trigger="{{ $header['overlay_ids']['search'] }}"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                            </svg>
                        </button>
                    @endif

                    @if ($header['cart'])
                        <button
                            type="button"
                            class="industrial-header__icon-action industrial-header__cart"
                            aria-label="{{ $header['cart']['label'] }}"
                            aria-haspopup="dialog"
                            aria-expanded="false"
                            aria-controls="{{ $header['overlay_ids']['cart'] }}"
                            data-header-overlay-trigger="{{ $header['overlay_ids']['cart'] }}"
                            data-cart-trigger
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L20 7H6m4 13a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm8 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                            </svg>
                            @if ($header['cart']['count'] > 0)
                                <span class="industrial-header__cart-badge" aria-hidden="true" data-cart-badge>
                                    {{ $header['cart']['badge'] }}
                                </span>
                            @endif
                        </button>
                    @endif

                    @include('partials.public-account-controls', [
                        'account' => $header['account'],
                        'iconOnlyGuest' => true,
                    ])

                    @include('partials.actions.render', [
                        'label' => $header['primary_action']['label'],
                        'class' => 'industrial-header__primary-action',
                        'presentation' => $header['primary_action']['presentation'],
                    ])

                </div>
            </div>
        </div>
    </div>
</header>

@if ($header['cart'])
    @include('partials.header.cart-drawer', [
        'cart' => $header['cart'],
        'overlayId' => $header['overlay_ids']['cart'],
    ])
@endif

@if ($header['search_url'])
    @include('partials.header.search-modal', [
        'searchUrl' => $header['search_url'],
        'searchSources' => $header['search_sources'],
        'overlayId' => $header['overlay_ids']['search'],
    ])
@endif
