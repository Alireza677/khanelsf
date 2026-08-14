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

                <div class="industrial-header__utilities">
                    @if ($header['search_url'])
                        <a
                            class="industrial-header__search"
                            href="{{ $header['search_url'] }}"
                            aria-label="جستجو در سایت"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                            </svg>
                        </a>
                    @endif

                    @include('partials.actions.render', [
                        'label' => $header['primary_action']['label'],
                        'class' => 'industrial-header__primary-action',
                        'presentation' => $header['primary_action']['presentation'],
                    ])

                    @include('partials.public-account-controls', ['account' => $header['account']])
                </div>
            </div>
        </div>
    </div>
</header>
