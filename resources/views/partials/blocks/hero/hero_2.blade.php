@php
    $alignment = ($data['hero_2_alignment'] ?? 'left') === 'right' ? 'right' : 'left';
    $videoUrl = trim((string) ($data['hero_2_video_url'] ?? ''));
    $videoPoster = trim((string) ($data['hero_2_video_poster'] ?? ''));
    $backgroundType = $data['hero_2_background_type'] ?? ($videoUrl ? 'video' : 'image');
    $backgroundType = $backgroundType === 'video' ? 'video' : 'image';
    $usesVideo = $backgroundType === 'video' && filled($videoUrl);
    $fallbackImage = $usesVideo ? ($videoPoster ?: ($data['image'] ?? null)) : ($data['image'] ?? null);
    $backgroundImage = filled($fallbackImage)
        ? "background-image: url('".e($fallbackImage)."');"
        : null;
    $backgroundVariables = \App\Support\BlockImageStyle::backgroundVariables($data, 'image');
    $blockHeight = trim((string) ($data['hero_2_height'] ?? ''));
    $blockHeight = is_numeric($blockHeight) ? max(0, (int) $blockHeight) : null;
    $heightVariable = $blockHeight ? "--hero-template-2-height: {$blockHeight}px;" : null;
    $heightClass = $blockHeight ? ' hero-template-2--fixed-height' : '';
    $sectionStyle = collect([$backgroundImage, $backgroundVariables, $heightVariable])
        ->filter()
        ->map(fn (string $style): string => trim($style, ' ;'))
        ->implode('; ');
    $selectorItems = collect($data['selector_items'] ?? [])
        ->filter(fn ($item) => filled($item['label'] ?? null) && filled($item['url'] ?? null))
        ->values();
    $buttonLabel = $data['primary_button_label'] ?? 'Get Started';
    $selectId = 'hero-template-2-select-'.substr(md5(json_encode($data)), 0, 10);
@endphp

@include('partials.blocks._image_control_styles')

<section
    class="content-block hero-template-2 hero-template-2--{{ $alignment }}{{ $heightClass }} block-configured-background"
    @if ($sectionStyle) style="{!! $sectionStyle !!}" @endif
>
    @if ($usesVideo)
        <video
            class="hero-template-2__background-video"
            muted
            loop
            playsinline
            preload="none"
            poster="{{ $videoPoster ?: ($data['image'] ?? '') }}"
            aria-hidden="true"
            data-hero-template-2-video
        >
            <source src="{{ $videoUrl }}">
        </video>
    @endif

    <div class="hero-template-2__inner">
        <div class="hero-template-2__content">
            @if (! empty($data['title']))
                <h1>{{ $data['title'] }}</h1>
            @endif

            @if (! empty($data['subtitle']))
                <p class="hero-template-2__description">{{ $data['subtitle'] }}</p>
            @elseif (! empty($data['description']))
                <p class="hero-template-2__description">{{ $data['description'] }}</p>
            @endif

            @if ($selectorItems->isNotEmpty())
                <div class="hero-template-2__selector" data-hero-template-2>
                    <label class="sr-only" for="{{ $selectId }}">
                        {{ $data['selector_placeholder'] ?? "من به دنبال..." }}
                    </label>

                    <select
                        id="{{ $selectId }}"
                        data-hero-template-2-select
                    >
                        <option value="">{{ $data['selector_placeholder'] ?? "من به دنبال ..." }}</option>
                        @foreach ($selectorItems as $item)
                            <option value="{{ $item['url'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>

                    <a
                        class="button hero-template-2__button"
                        href="#"
                        aria-disabled="true"
                        data-hero-template-2-button
                    >
                        {{ $buttonLabel }}
                    </a>
                </div>
            @elseif (! empty($data['primary_button_url']))
                <a class="button hero-template-2__button" href="{{ $data['primary_button_url'] }}">
                    {{ $buttonLabel }}
                </a>
            @endif

            @if (! empty($data['secondary_button_label']) && ! empty($data['secondary_button_url']))
                <p class="hero-template-2__helper">
                    <a href="{{ $data['secondary_button_url'] }}">{{ $data['secondary_button_label'] }}</a>
                </p>
            @endif
        </div>
    </div>
</section>
