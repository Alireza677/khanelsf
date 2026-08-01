<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    @php
        $seo = $seo ?? app(\App\Services\SeoService::class)->make();
    @endphp

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($googleSiteVerification = app(\App\Services\SettingsService::class)->googleSiteVerification())
        <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
    @endif
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

    <link rel="stylesheet" href="{{ asset('assets/iconsax/outline/style.css') }}">

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

                    const close = function (restoreFocus = false) {
                        const wasOpen = header.classList.contains('is-nav-open')

                        header.classList.remove('is-nav-open')
                        toggle.setAttribute('aria-expanded', 'false')
                        toggle.setAttribute('aria-label', 'باز کردن منوی اصلی')

                        if (wasOpen && header.classList.contains('industrial-header')) {
                            document.body.classList.remove('industrial-mobile-menu-open')
                        }

                        if (restoreFocus) {
                            toggle.focus()
                        }
                    }

                    const open = function () {
                        header.classList.add('is-nav-open')
                        toggle.setAttribute('aria-expanded', 'true')
                        toggle.setAttribute('aria-label', 'بستن منوی اصلی')

                        if (header.classList.contains('industrial-header')) {
                            document.body.classList.add('industrial-mobile-menu-open')
                        }
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
                        if (event.key === 'Escape' && header.classList.contains('is-nav-open')) {
                            close(true)
                        }
                    })

                    window.addEventListener('resize', function () {
                        if (window.innerWidth > 900) {
                            close()
                        }
                    })
                })
            }

            const initIndustrialStickyHeader = function () {
                document.querySelectorAll('.industrial-header:not(.industrial-header--static)').forEach(function (header) {
                    if (
                        header.dataset.stickyActionsInitialized === 'true'
                        || ! header.querySelector('.industrial-header__top-actions')
                    ) {
                        return
                    }

                    header.dataset.stickyActionsInitialized = 'true'

                    let lastScrollY = Math.max(window.scrollY, 0)
                    let scrollFrame

                    const update = function () {
                        const currentScrollY = Math.max(window.scrollY, 0)

                        if (currentScrollY <= 16 || currentScrollY < lastScrollY) {
                            header.classList.remove('is-top-actions-hidden')
                        } else if (currentScrollY > lastScrollY) {
                            header.classList.add('is-top-actions-hidden')
                        }

                        lastScrollY = currentScrollY
                        scrollFrame = undefined
                    }

                    window.addEventListener('scroll', function () {
                        if (scrollFrame !== undefined) {
                            return
                        }

                        scrollFrame = requestAnimationFrame(update)
                    }, { passive: true })
                })
            }

            const initDesktopNavigationOverflow = function () {
                document.querySelectorAll('[data-desktop-navigation]').forEach(function (navigation) {
                    if (navigation.dataset.desktopOverflowInitialized === 'true') {
                        return
                    }

                    const more = navigation.querySelector(':scope > [data-navigation-more]')
                    const moreTrigger = more?.querySelector('[data-navigation-more-trigger]')
                    const moreItems = more?.querySelector('[data-navigation-more-items]')

                    if (! more || ! moreTrigger || ! moreItems) {
                        return
                    }

                    navigation.dataset.desktopOverflowInitialized = 'true'

                    const closeMore = function (restoreFocus = false) {
                        more.classList.remove('is-open')
                        moreTrigger.setAttribute('aria-expanded', 'false')

                        if (restoreFocus) {
                            moreTrigger.focus()
                        }
                    }

                    const restoreItems = function () {
                        Array.from(moreItems.children).forEach(function (item) {
                            navigation.insertBefore(item, more)
                        })

                        more.hidden = true
                        closeMore()
                    }

                    const fitItems = function () {
                        restoreItems()

                        if (window.innerWidth <= 900) {
                            return
                        }

                        const candidates = function () {
                            return Array.from(navigation.children).filter(function (item) {
                                return item !== more
                            })
                        }

                        while (navigation.scrollWidth > navigation.clientWidth && candidates().length > 0) {
                            more.hidden = false
                            moreItems.prepend(candidates().at(-1))
                        }

                        if (moreItems.children.length === 0) {
                            more.hidden = true
                        }
                    }

                    let fitFrame
                    const scheduleFit = function () {
                        cancelAnimationFrame(fitFrame)
                        fitFrame = requestAnimationFrame(fitItems)
                    }

                    moreTrigger.addEventListener('click', function (event) {
                        event.stopPropagation()
                        const willOpen = ! more.classList.contains('is-open')

                        closeMore()

                        if (willOpen) {
                            more.classList.add('is-open')
                            moreTrigger.setAttribute('aria-expanded', 'true')
                        }
                    })

                    document.addEventListener('click', function (event) {
                        if (! more.contains(event.target)) {
                            closeMore()
                        }
                    })

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && more.classList.contains('is-open')) {
                            closeMore(true)
                        }
                    })

                    window.addEventListener('resize', scheduleFit)
                    document.fonts?.ready.then(scheduleFit)
                    scheduleFit()
                })
            }

            const initActionPlaceholders = function () {
                if (window.__actionPlaceholdersInitialized) {
                    return
                }

                window.__actionPlaceholdersInitialized = true

                document.addEventListener('click', function (event) {
                    const placeholder = event.target.closest('a[data-action-placeholder][href="#"]')

                    if (placeholder) {
                        event.preventDefault()
                    }
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
                initIndustrialStickyHeader()
                initDesktopNavigationOverflow()
                initActionPlaceholders()
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

    @if ($siteHeaderTemplate?->hasBlocks())
        @include('partials.page-blocks', [
            'blocks' => $siteHeaderTemplate->blocks,
            'context' => [
                'kind' => 'site',
                'type' => 'site_header',
                'preview' => ! empty($isPreview),
                'page_url' => request()->getRequestUri(),
            ],
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

    @include('partials.action-form-modal-script')
</body>
</html>
