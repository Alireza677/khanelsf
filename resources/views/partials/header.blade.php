@php
    $settings = app(\App\Services\SettingsService::class);

    $siteName = $settings->siteName();
    $logoUrl = $settings->logoUrl();
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

            <x-navigation placement="header" variant="header" />

            <div class="header-actions">
                @if ($ctaLabel && $ctaUrl)
                    <a class="header-cta" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>
                @endif
                @include('partials.public-account-controls', ['account' => $account])
            </div>
        </div>
    </div>
</header>
