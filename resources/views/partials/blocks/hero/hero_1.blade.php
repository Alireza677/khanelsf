@php
    $requestedTheme = $data['hero_1_theme'] ?? 'image';
    $theme = match ($requestedTheme) {
        'light_grid' => 'light-grid',
        'animated_dotted_surface' => 'animated-dotted-surface',
        default => 'image',
    };
    $animatedBackgroundEnabled = $theme === 'animated-dotted-surface'
        && filter_var($data['animated_background_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $animatedBackgroundInteractive = filter_var($data['animated_background_interactive'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $animatedBackgroundDensity = in_array($data['animated_background_density'] ?? null, ['low', 'medium', 'high'], true)
        ? $data['animated_background_density']
        : 'medium';
    $animatedBackgroundSpeed = in_array($data['animated_background_speed'] ?? null, ['slow', 'normal', 'fast'], true)
        ? $data['animated_background_speed']
        : 'slow';
    $animatedBackgroundOpacity = max(0.1, min(1, (float) ($data['animated_background_opacity'] ?? 0.45)));
    $overlayOpacity = (int) ($data['overlay_opacity'] ?? 45);
    $overlayOpacity = max(0, min(90, $overlayOpacity));
    $overlayStart = number_format($overlayOpacity / 100, 2, '.', '');
    $overlayEnd = number_format(min(85, $overlayOpacity + 18) / 100, 2, '.', '');
    $backgroundImage = $theme === 'image' && filled($data['image'] ?? null)
        ? "background-image: linear-gradient(rgba(15, 23, 42, {$overlayStart}), rgba(15, 23, 42, {$overlayEnd})), url('".e($data['image'])."');"
        : null;
    $backgroundVariables = $theme === 'image' ? \App\Support\BlockImageStyle::backgroundVariables($data, 'image') : null;
    $backgroundVariables = $theme === 'image' && blank($backgroundVariables) ? '--block-background-size: cover' : $backgroundVariables;
    $desktopHeight = trim((string) ($data['hero_1_desktop_height'] ?? $data['hero_1_height'] ?? ''));
    $desktopHeight = is_numeric($desktopHeight) ? max(0, (int) $desktopHeight) : null;
    $mobileHeight = trim((string) ($data['hero_1_mobile_height'] ?? ''));
    $mobileHeight = is_numeric($mobileHeight) ? max(0, (int) $mobileHeight) : null;
    $desktopHeightVariable = $desktopHeight ? "--hero-template-1-height: {$desktopHeight}px;" : null;
    $mobileHeightVariable = $mobileHeight ? "--hero-template-1-mobile-height: {$mobileHeight}px;" : null;
    $heightClass = $desktopHeight ? 'hero-template-1--fixed-height' : null;
    $mobileHeightClass = $mobileHeight ? 'hero-template-1--mobile-fixed-height' : null;
    $sectionStyle = collect([$backgroundImage, $backgroundVariables, $desktopHeightVariable, $mobileHeightVariable])
        ->filter()
        ->map(fn (string $style): string => trim($style, ' ;'))
        ->implode('; ');
    $description = $data['subtitle'] ?? $data['description'] ?? null;
    $secondLine = trim((string) ($data['hero_1_title_second_line'] ?? ''));
    $showUnderline = (bool) ($data['hero_1_show_underline'] ?? false);
    $requestedHeadingTag = $data['heading_tag'] ?? 'h2';
    $headingTag = in_array($requestedHeadingTag, ['h1', 'h2'], true) ? $requestedHeadingTag : 'h2';
    $socialLinks = collect($data['hero_1_social_links'] ?? [])
        ->filter(fn ($item) => filled($item['label'] ?? null) && filled($item['url'] ?? null))
        ->values();
@endphp

@include('partials.blocks._image_control_styles')

<section
    @class([
        'content-block',
        'hero-template-1',
        "hero-template-1--{$theme}",
        $heightClass,
        $mobileHeightClass,
        'block-configured-background' => $theme === 'image',
    ])
    @if ($sectionStyle) style="{!! $sectionStyle !!}" @endif
>
    @if ($animatedBackgroundEnabled)
        <div
            class="hero-dotted-surface"
            data-hero-dotted-surface
            data-theme="dark"
            data-density="{{ $animatedBackgroundDensity }}"
            data-speed="{{ $animatedBackgroundSpeed }}"
            data-opacity="{{ $animatedBackgroundOpacity }}"
            data-interactive="{{ $animatedBackgroundInteractive ? 'true' : 'false' }}"
            aria-hidden="true"
        ></div>
    @endif

    <div class="hero-template-1__inner">
        @if (! empty($data['eyebrow']))
            <p class="hero-template-1__eyebrow">
                @if (! empty($data['hero_1_eyebrow_icon']))
                    <span class="hero-template-1__eyebrow-icon" aria-hidden="true">
                        @include('partials.blocks._icon', ['icon' => $data['hero_1_eyebrow_icon'], 'size' => $data['hero_1_eyebrow_icon_size'] ?? null])
                    </span>
                @endif
                <span>{{ $data['eyebrow'] }}</span>
            </p>
        @endif

        @if (! empty($data['title']))
            <{{ $headingTag }} @class(['block-title', 'hero-template-1__title--decorated' => $showUnderline])>
                <span class="hero-template-1__title-main">{{ $data['title'] }}</span>
                @if ($showUnderline)
                    <span class="hero-template-1__underline" aria-hidden="true"></span>
                @endif
                @if ($secondLine !== '')
                    <span class="hero-template-1__title-second">{{ $secondLine }}</span>
                @endif
            </{{ $headingTag }}>
        @endif

        @if (! empty($description))
            <p class="hero-template-1__description">{{ $description }}</p>
        @endif

        @if ((! empty($data['primary_button_label']) && ! empty($data['primary_button_url'])) || (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url'])))
            <div class="hero-template-1__actions">
                @if (! empty($data['primary_button_label']) && ! empty($data['primary_button_url']))
                    <a class="button" href="{{ $data['primary_button_url'] }}">{{ $data['primary_button_label'] }}</a>
                @endif

                @if (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url']))
                    <a class="button hero-template-1__secondary" href="{{ $data['secondary_button_url'] }}">{{ $data['secondary_button_label'] }}</a>
                @endif
            </div>
        @endif

        @if ($socialLinks->isNotEmpty() || ! empty($data['hero_1_scroll_label']))
            <div class="hero-template-1__footer">
                @if ($socialLinks->isNotEmpty())
                    <div class="hero-template-1__socials">
                        @foreach ($socialLinks as $link)
                            <a class="hero-template-1__social" href="{{ $link['url'] }}" aria-label="{{ $link['label'] }}">
                                @include('partials.blocks._icon', ['icon' => $link['icon'] ?? null, 'fallback' => $link['label'], 'size' => $link['icon_size'] ?? null])
                            </a>
                        @endforeach
                    </div>
                @endif

                @if (! empty($data['hero_1_scroll_label']))
                    <span class="hero-template-1__scroll">
                        <span>{{ $data['hero_1_scroll_label'] }}</span>
                        <span aria-hidden="true">&darr;</span>
                    </span>
                @endif
            </div>
        @endif
    </div>
</section>
