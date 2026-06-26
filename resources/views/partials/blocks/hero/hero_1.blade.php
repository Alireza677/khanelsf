@php
    $theme = ($data['hero_1_theme'] ?? 'image') === 'light_grid' ? 'light-grid' : 'image';
    $overlayOpacity = (int) ($data['overlay_opacity'] ?? 45);
    $overlayOpacity = max(0, min(90, $overlayOpacity));
    $overlayStart = number_format($overlayOpacity / 100, 2, '.', '');
    $overlayEnd = number_format(min(85, $overlayOpacity + 18) / 100, 2, '.', '');
    $backgroundImage = $theme === 'image' && filled($data['image'] ?? null)
        ? "background-image: linear-gradient(rgba(15, 23, 42, {$overlayStart}), rgba(15, 23, 42, {$overlayEnd})), url('".e($data['image'])."');"
        : null;
    $backgroundVariables = $theme === 'image' ? \App\Support\BlockImageStyle::backgroundVariables($data, 'image') : null;
    $backgroundVariables = $theme === 'image' && blank($backgroundVariables) ? '--block-background-size: cover' : $backgroundVariables;
    $description = $data['subtitle'] ?? $data['description'] ?? null;
    $secondLine = trim((string) ($data['hero_1_title_second_line'] ?? ''));
    $showUnderline = (bool) ($data['hero_1_show_underline'] ?? false);
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
        'block-configured-background' => $theme === 'image',
    ])
    @if ($backgroundImage || $backgroundVariables) style="{!! trim($backgroundImage.' '.$backgroundVariables, ' ;') !!}" @endif
>
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
            <h1 @class(['hero-template-1__title--decorated' => $showUnderline])>
                <span class="hero-template-1__title-main">{{ $data['title'] }}</span>
                @if ($showUnderline)
                    <span class="hero-template-1__underline" aria-hidden="true"></span>
                @endif
                @if ($secondLine !== '')
                    <span class="hero-template-1__title-second">{{ $secondLine }}</span>
                @endif
            </h1>
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
