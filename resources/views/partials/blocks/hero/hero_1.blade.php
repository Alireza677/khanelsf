@php
    $content = $hero['content'];
    $settings = $hero['settings'];
    $media = $content['media'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $effect = $settings['background_effect'];
    $theme = match ($settings['background_treatment']) {
        'light_grid' => 'light-grid',
        'animated_dotted_surface' => 'animated-dotted-surface',
        'animated_paths' => 'animated-paths',
        default => 'image',
    };
    $animatedBackgroundEnabled = $theme === 'animated-dotted-surface' && $effect['enabled'];
    $animatedBackgroundInteractive = $effect['interactive'];
    $animatedBackgroundDensity = $effect['density'];
    $animatedBackgroundSpeed = $effect['speed'];
    $animatedBackgroundOpacity = $effect['opacity'];
    $animatedBackgroundColor = $effect['background_color_override'] ?? '#08132a';
    $animatedDotsColor = $effect['foreground_color_override'] ?? '#dbe7ff';
    $pathsBackgroundColor = $effect['background_color_override'] ?? '#0b1220';
    $pathsColor = $effect['foreground_color_override'] ?? '#ffffff';
    $pathsOpacity = $effect['opacity'];
    $pathsSpeed = $effect['speed'];
    $pathsDensity = $effect['density'];
    $pathsCount = ['low' => 24, 'medium' => 36, 'high' => 48][$pathsDensity];
    $pathsLineWidth = $effect['settings']['line_width'] ?? 1;
    $pathsAnimationEnabled = $effect['enabled'];
    $pathsDurationBase = ['slow' => 28, 'normal' => 18, 'fast' => 10][$pathsSpeed];
    $pathsDurationRange = ['slow' => 13, 'normal' => 13, 'fast' => 9][$pathsSpeed];
    $overlayOpacity = $settings['overlay_opacity'];
    $overlayStart = number_format($overlayOpacity / 100, 2, '.', '');
    $overlayEnd = number_format(min(85, $overlayOpacity + 18) / 100, 2, '.', '');
    $backgroundImage = $theme === 'image' && filled($media['url'])
        ? "background-image: linear-gradient(rgba(15, 23, 42, {$overlayStart}), rgba(15, 23, 42, {$overlayEnd})), url('".e($media['url'])."');"
        : null;
    $backgroundVariables = $theme === 'image' ? \App\Support\BlockImageStyle::normalizedBackgroundVariables($settings['media']) : null;
    $backgroundVariables = $theme === 'image' && blank($backgroundVariables) ? '--block-background-size: cover' : $backgroundVariables;
    $desktopHeight = $settings['height']['desktop'];
    $mobileHeight = $settings['height']['mobile'];
    $desktopHeightVariable = $desktopHeight ? "--hero-template-1-height: {$desktopHeight}px;" : null;
    $mobileHeightVariable = $mobileHeight ? "--hero-template-1-mobile-height: {$mobileHeight}px;" : null;
    $animatedBackgroundColorVariable = $theme === 'animated-dotted-surface' ? "--hero-animated-background-color: {$animatedBackgroundColor};" : null;
    $pathsVariables = $theme === 'animated-paths' ? "--hero-paths-background: {$pathsBackgroundColor}; --hero-paths-color: {$pathsColor};" : null;
    $heightClass = $desktopHeight ? 'hero-template-1--fixed-height' : null;
    $mobileHeightClass = $mobileHeight ? 'hero-template-1--mobile-fixed-height' : null;
    $sectionStyle = collect([$backgroundImage, $backgroundVariables, $desktopHeightVariable, $mobileHeightVariable, $animatedBackgroundColorVariable, $pathsVariables])->filter()->map(fn (string $style): string => trim($style, ' ;'))->implode('; ');
    $description = $content['lead'] ?? $content['description'];
    $secondLine = trim((string) ($content['title_secondary'] ?? ''));
    $showUnderline = $settings['title_decoration'] === 'underline';
    $headingTag = $settings['heading_tag'];
    $socialLinks = collect($content['social_links'])->filter(fn ($item) => filled($item['label'] ?? null) && filled($item['url'] ?? null))->values();
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
    @if ($theme === 'animated-paths')
        <div
            @class(['hero-animated-paths', 'is-animation-disabled' => ! $pathsAnimationEnabled])
            data-hero-animated-paths
            data-speed="{{ $pathsSpeed }}"
            data-density="{{ $pathsDensity }}"
            data-animation-enabled="{{ $pathsAnimationEnabled ? 'true' : 'false' }}"
            aria-hidden="true"
        >
            @foreach ([1, -1] as $position)
                <svg class="hero-animated-paths__svg" viewBox="0 0 696 316" fill="none" preserveAspectRatio="xMidYMid slice">
                    @for ($index = 0; $index < $pathsCount; $index++)
                        @php
                            $path = sprintf(
                                'M-%s -%sC-%s -%s -%s %s %s %sC%s %s %s %s %s %s',
                                380 - $index * 5 * $position,
                                189 + $index * 6,
                                380 - $index * 5 * $position,
                                189 + $index * 6,
                                312 - $index * 5 * $position,
                                216 - $index * 6,
                                152 - $index * 5 * $position,
                                343 - $index * 6,
                                616 - $index * 5 * $position,
                                470 - $index * 6,
                                684 - $index * 5 * $position,
                                875 - $index * 6,
                                684 - $index * 5 * $position,
                                875 - $index * 6,
                            );
                        @endphp
                        <path
                            d="{{ $path }}"
                            pathLength="1"
                            stroke="{{ $pathsColor }}"
                            stroke-opacity="{{ number_format($pathsOpacity * (0.45 + 0.55 * ($index + 1) / $pathsCount), 3, '.', '') }}"
                            stroke-width="{{ number_format($pathsLineWidth * (0.5 + $index * 0.03), 2, '.', '') }}"
                            style="--path-index: {{ $index }}; --path-duration: {{ $pathsDurationBase + ($index % $pathsDurationRange) }}s;"
                        />
                    @endfor
                </svg>
            @endforeach
        </div>
    @endif

    @if ($animatedBackgroundEnabled)
        <div
            class="hero-dotted-surface"
            data-hero-dotted-surface
            data-theme="dark"
            data-density="{{ $animatedBackgroundDensity }}"
            data-speed="{{ $animatedBackgroundSpeed }}"
            data-opacity="{{ $animatedBackgroundOpacity }}"
            data-bg-color="{{ $animatedBackgroundColor }}"
            data-dots-color="{{ $animatedDotsColor }}"
            data-interactive="{{ $animatedBackgroundInteractive ? 'true' : 'false' }}"
            aria-hidden="true"
        ></div>
    @endif

    <div class="hero-template-1__inner">
        @if (! empty($content['eyebrow']['text']))
            <p class="hero-template-1__eyebrow">
                @if (! empty($content['eyebrow']['icon']))
                    <span class="hero-template-1__eyebrow-icon" aria-hidden="true">
                        @include('partials.blocks._icon', ['icon' => $content['eyebrow']['icon'], 'size' => $settings['eyebrow_icon_size'] ?? null])
                    </span>
                @endif
                <span>{{ $content['eyebrow']['text'] }}</span>
            </p>
        @endif

        @if (! empty($content['title']))
            <{{ $headingTag }} @class(['block-title', 'hero-template-1__title--decorated' => $showUnderline])>
                <span class="hero-template-1__title-main">{{ $content['title'] }}</span>
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

        @if ((! empty($primaryCta['label']) && ! empty($primaryCta['url'])) || (! empty($secondaryCta['label']) && ! empty($secondaryCta['url'])))
            <div class="hero-template-1__actions">
                @if (! empty($primaryCta['label']) && ! empty($primaryCta['url']))
                    <a class="button" href="{{ $primaryCta['url'] }}">{{ $primaryCta['label'] }}</a>
                @endif

                @if (! empty($secondaryCta['label']) && ! empty($secondaryCta['url']))
                    <a class="button hero-template-1__secondary" href="{{ $secondaryCta['url'] }}">{{ $secondaryCta['label'] }}</a>
                @endif
            </div>
        @endif

        @if ($socialLinks->isNotEmpty() || ! empty($content['scroll_label']))
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

                @if (! empty($content['scroll_label']))
                    <span class="hero-template-1__scroll">
                        <span>{{ $content['scroll_label'] }}</span>
                        <span aria-hidden="true">&darr;</span>
                    </span>
                @endif
            </div>
        @endif
    </div>
</section>
