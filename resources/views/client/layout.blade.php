<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'پرتال مشتریان')</title>
    @include('partials.theme')
    <style>{!! file_get_contents(resource_path('css/client-portal.css')) !!}</style>
</head>
<body class="client-portal">
    @auth('client')
        @php($portalNavigation = app(\App\Services\ClientPortalNavigation::class)->items())
        <header class="portal-header">
            <div class="portal-header__inner">
                <button class="portal-menu-toggle" type="button" aria-controls="portal-sidebar" aria-expanded="false" data-portal-menu-toggle>
                    <span></span><span></span><span></span>
                    <span class="sr-only">نمایش منوی پرتال</span>
                </button>
                <a class="portal-brand" href="{{ route('client.dashboard', $portalCustomer ? ['customer' => $portalCustomer->id] : []) }}">
                    <span class="portal-brand__mark">CP</span>
                    <span><strong>پرتال مشتریان</strong><small>فضای اختصاصی شما</small></span>
                </a>
                <div class="portal-header__identity">
                    <span>{{ $portalUser->name }}</span>
                    <strong>{{ $portalCustomer?->display_name ?? 'بدون حساب مشتری' }}</strong>
                </div>
            </div>
        </header>

        <div class="portal-shell">
            <div class="portal-backdrop" data-portal-backdrop></div>
            <aside class="portal-sidebar" id="portal-sidebar" data-portal-sidebar>
                <nav aria-label="ناوبری پرتال مشتریان">
                    <ul class="portal-menu">
                        @foreach ($portalNavigation as $item)
                            <li>
                                <a href="{{ route($item['route'], $portalCustomer ? ['customer' => $portalCustomer->id] : []) }}" class="portal-menu__item @if(request()->routeIs($item['active_routes'] ?? $item['route'])) is-active @endif">
                                    <span class="portal-menu__icon" aria-hidden="true">{{ $item['icon'] }}</span>
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['coming_soon'] ?? false)<small>به‌زودی</small>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
                <form method="POST" action="{{ route('client.logout') }}" class="portal-logout">
                    @csrf
                    <button type="submit">خروج از حساب</button>
                </form>
            </aside>

            <main class="portal-main">
                @yield('content')
            </main>
        </div>
    @else
        <main class="portal-auth-main">@yield('content')</main>
    @endauth

    <script>
        const portalToggle = document.querySelector('[data-portal-menu-toggle]')
        const portalSidebar = document.querySelector('[data-portal-sidebar]')
        const portalBackdrop = document.querySelector('[data-portal-backdrop]')
        const closePortalMenu = () => {
            document.body.classList.remove('portal-menu-open')
            portalToggle?.setAttribute('aria-expanded', 'false')
        }
        portalToggle?.addEventListener('click', () => {
            const isOpen = document.body.classList.toggle('portal-menu-open')
            portalToggle.setAttribute('aria-expanded', String(isOpen))
        })
        portalBackdrop?.addEventListener('click', closePortalMenu)
        portalSidebar?.addEventListener('click', event => {
            if (event.target.closest('a')) closePortalMenu()
        })
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closePortalMenu()
        })
    </script>
</body>
</html>
