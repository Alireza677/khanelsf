<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    @php
        $seo = $seo ?? app(\App\Services\SeoService::class)->make();
    @endphp

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->metaTitle() }}</title>
    @if ($seo->metaDescription())
        <meta name="description" content="{{ $seo->metaDescription() }}">
    @endif
    <meta name="robots" content="{{ $seo->robots }}">
    @if ($seo->canonicalUrl)
        <link rel="canonical" href="{{ $seo->canonicalUrl }}">
    @endif

    <meta property="og:title" content="{{ $seo->openGraphTitle() }}">
    @if ($seo->openGraphDescription())
        <meta property="og:description" content="{{ $seo->openGraphDescription() }}">
    @endif
    @if ($seo->canonicalUrl)
        <meta property="og:url" content="{{ $seo->canonicalUrl }}">
    @endif
    <meta property="og:type" content="{{ $seo->ogType }}">
    <meta property="og:site_name" content="{{ app(\App\Services\SettingsService::class)->siteName() }}">
    @if ($seo->ogImage)
        <meta property="og:image" content="{{ $seo->ogImage }}">
    @endif

    <meta name="twitter:card" content="{{ $seo->twitterCard }}">
    <meta name="twitter:title" content="{{ $seo->openGraphTitle() }}">
    @if ($seo->openGraphDescription())
        <meta name="twitter:description" content="{{ $seo->openGraphDescription() }}">
    @endif
    @if ($seo->ogImage)
        <meta name="twitter:image" content="{{ $seo->ogImage }}">
    @endif

    @if ($seo->schema)
        <script type="application/ld+json">@json($seo->schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endif
    @if (app(\App\Services\SettingsService::class)->faviconUrl())
        <link rel="icon" href="{{ app(\App\Services\SettingsService::class)->faviconUrl() }}">
    @endif

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            {!! file_get_contents(resource_path('css/app.css')) !!}
        </style>
        <script>
            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-gallery-lightbox-src]')

                if (! trigger) {
                    return
                }

                event.preventDefault()

                const overlay = document.createElement('div')
                overlay.className = 'gallery-lightbox'
                overlay.setAttribute('role', 'dialog')
                overlay.setAttribute('aria-modal', 'true')
                overlay.innerHTML = '<button type="button" class="gallery-lightbox__close" aria-label="بستن پیش‌نمایش تصویر">&times;</button><img alt="">'
                overlay.querySelector('img').src = trigger.getAttribute('data-gallery-lightbox-src')
                overlay.querySelector('img').alt = trigger.getAttribute('data-gallery-lightbox-alt') || ''
                document.body.appendChild(overlay)
                document.body.classList.add('gallery-lightbox-open')
                overlay.querySelector('button').focus()
            })

            document.addEventListener('click', function (event) {
                const overlay = document.querySelector('.gallery-lightbox')

                if (overlay && (event.target === overlay || event.target.closest('.gallery-lightbox__close'))) {
                    overlay.remove()
                    document.body.classList.remove('gallery-lightbox-open')
                }
            })

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    document.querySelector('.gallery-lightbox')?.remove()
                    document.body.classList.remove('gallery-lightbox-open')
                }
            })

            const initMobileHeader = function () {
                document.querySelectorAll('[data-site-header]').forEach(function (header) {
                    if (header.dataset.mobileHeaderInitialized === 'true') {
                        return
                    }

                    header.dataset.mobileHeaderInitialized = 'true'

                    const toggle = header.querySelector('[data-menu-toggle]')
                    const nav = header.querySelector('[data-site-nav]')

                    if (! toggle || ! nav) {
                        return
                    }

                    const close = function () {
                        header.classList.remove('is-nav-open')
                        toggle.setAttribute('aria-expanded', 'false')
                    }

                    const open = function () {
                        header.classList.add('is-nav-open')
                        toggle.setAttribute('aria-expanded', 'true')
                    }

                    toggle.addEventListener('click', function () {
                        if (header.classList.contains('is-nav-open')) {
                            close()
                        } else {
                            open()
                        }
                    })

                    nav.addEventListener('click', function (event) {
                        if (event.target.closest('a')) {
                            close()
                        }
                    })

                    document.addEventListener('click', function (event) {
                        if (! header.contains(event.target)) {
                            close()
                        }
                    })

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            close()
                        }
                    })

                    window.addEventListener('resize', function () {
                        if (window.innerWidth > 900) {
                            close()
                        }
                    })
                })
            }

            const initHeroTemplateSelectors = function () {
                document.querySelectorAll('[data-hero-template-2]').forEach(function (root) {
                    const select = root.querySelector('[data-hero-template-2-select]')
                    const button = root.querySelector('[data-hero-template-2-button]')

                    if (! select || ! button) {
                        return
                    }

                    const sync = function () {
                        if (select.value) {
                            button.setAttribute('href', select.value)
                            button.setAttribute('aria-disabled', 'false')
                        } else {
                            button.setAttribute('href', '#')
                            button.setAttribute('aria-disabled', 'true')
                        }
                    }

                    select.addEventListener('change', sync)
                    button.addEventListener('click', function (event) {
                        if (button.getAttribute('aria-disabled') === 'true') {
                            event.preventDefault()
                        }
                    })
                    sync()
                })
            }

            const initHeroTemplateVideos = function () {
                const playVideos = function () {
                    document.querySelectorAll('[data-hero-template-2-video]').forEach(function (video) {
                        if (video.dataset.heroTemplate2VideoStarted === 'true') {
                            return
                        }

                        video.dataset.heroTemplate2VideoStarted = 'true'
                        video.play().catch(function () {
                            video.dataset.heroTemplate2VideoStarted = 'false'
                        })
                    })
                }

                if (document.readyState === 'complete') {
                    playVideos()

                    return
                }

                window.addEventListener('load', playVideos, { once: true })
            }

            const initStatsCounters = function () {
                const counters = Array.from(document.querySelectorAll('[data-stats-counter]')).filter(function (counter) {
                    if (counter.dataset.statsCounterInitialized === 'true') {
                        return false
                    }

                    counter.dataset.statsCounterInitialized = 'true'

                    return true
                })

                if (! counters.length) {
                    return
                }

                const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
                const finish = function (counter) {
                    counter.textContent = counter.dataset.counterFormatted || counter.textContent
                }

                const formatNumber = function (value, useGrouping) {
                    return useGrouping ? Math.round(value).toLocaleString('en-US') : String(Math.round(value))
                }

                const animate = function (counter) {
                    if (counter.dataset.statsCounterAnimated === 'true') {
                        return
                    }

                    counter.dataset.statsCounterAnimated = 'true'

                    const target = Number.parseInt(counter.dataset.counterTarget || '0', 10)
                    const prefix = counter.dataset.counterPrefix || ''
                    const suffix = counter.dataset.counterSuffix || ''
                    const formatted = counter.dataset.counterFormatted || ''
                    const useGrouping = formatted.includes(',')

                    if (! Number.isFinite(target) || target <= 0 || prefersReducedMotion) {
                        finish(counter)

                        return
                    }

                    const duration = 1400
                    const start = performance.now()

                    const tick = function (now) {
                        const progress = Math.min((now - start) / duration, 1)
                        const eased = 1 - Math.pow(1 - progress, 3)
                        counter.textContent = `${prefix}${formatNumber(target * eased, useGrouping)}${suffix}`

                        if (progress < 1) {
                            window.requestAnimationFrame(tick)
                        } else {
                            finish(counter)
                        }
                    }

                    counter.textContent = `${prefix}0${suffix}`
                    window.requestAnimationFrame(tick)
                }

                if (! ('IntersectionObserver' in window)) {
                    counters.forEach(finish)

                    return
                }

                const observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (! entry.isIntersecting) {
                            return
                        }

                        animate(entry.target)
                        observer.unobserve(entry.target)
                    })
                }, { threshold: 0.35 })

                counters.forEach(function (counter) {
                    observer.observe(counter)
                })
            }

            const initPublicInteractions = function () {
                initMobileHeader()
                initHeroTemplateSelectors()
                initHeroTemplateVideos()
                initStatsCounters()
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPublicInteractions)
            } else {
                initPublicInteractions()
            }
        </script>
    @endif
    @include('partials.theme')
</head>
<body>
    @if (! empty($isPreview))
        <div class="preview-banner">
            پیش‌نمایش مدیر. این صفحه ممکن است برای کاربران عمومی قابل مشاهده نباشد.
        </div>
    @endif

    @php
        $templateService = app(\App\Services\TemplateService::class);
        $siteHeaderTemplate = $templateService->findTemplateFor('site_header');
        $siteFooterTemplate = $templateService->findTemplateFor('site_footer');
    @endphp

    @if ($siteHeaderTemplate?->hasBlocks())
        @include('partials.page-blocks', [
            'blocks' => $siteHeaderTemplate->blocks,
            'context' => ['kind' => 'site', 'type' => 'site_header'],
        ])
    @else
        @include('partials.header')
    @endif

    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>

    @if ($siteFooterTemplate?->hasBlocks())
        @include('partials.page-blocks', [
            'blocks' => $siteFooterTemplate->blocks,
            'context' => ['kind' => 'site', 'type' => 'site_footer'],
        ])
    @else
        @include('partials.footer')
    @endif
</body>
</html>
