@php
    $content = $hero['content'];
    $settings = $hero['settings'];
    $media = $content['media'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $selector = $content['selector'];
    $alignment = $settings['alignment'] === 'right' ? 'right' : 'left';
    $videoUrl = trim((string) ($media['video_url'] ?? ''));
    $videoPoster = trim((string) ($media['poster_url'] ?? ''));
    $usesVideo = $settings['background_treatment'] === 'video' && filled($videoUrl);
    $fallbackImage = $usesVideo ? ($videoPoster ?: $media['url']) : $media['url'];
    $backgroundImage = filled($fallbackImage) ? "background-image: url('".e($fallbackImage)."');" : null;
    $backgroundVariables = \App\Support\BlockImageStyle::normalizedBackgroundVariables($settings['media']);
    $blockHeight = is_numeric($settings['height']['desktop']) ? max(0, (int) $settings['height']['desktop']) : null;
    $heightVariable = $blockHeight ? "--hero-template-2-height: {$blockHeight}px;" : null;
    $heightClass = $blockHeight ? ' hero-template-2--fixed-height' : '';
    $sectionStyle = collect([$backgroundImage, $backgroundVariables, $heightVariable])->filter()->map(fn (string $style): string => trim($style, ' ;'))->implode('; ');
    $selectorItems = collect($selector['items'] ?? [])->filter(fn ($item) => filled($item['label'] ?? null) && filled($item['url'] ?? null))->values();
    $selectorPlaceholder = $selector['placeholder'] ?? null;
    $buttonLabel = $primaryCta['label'] ?? 'Get Started';
    $selectId = 'hero-template-2-select-'.substr(md5(json_encode($hero)), 0, 10);
@endphp

@include('partials.blocks._image_control_styles')

<section class="content-block hero-template-2 hero-template-2--{{ $alignment }}{{ $heightClass }} block-configured-background" @if ($sectionStyle) style="{!! $sectionStyle !!}" @endif>
    @if ($usesVideo)
        <video class="hero-template-2__background-video" muted loop playsinline preload="none" poster="{{ $videoPoster ?: ($media['url'] ?? '') }}" aria-hidden="true" data-hero-template-2-video>
            <source src="{{ $videoUrl }}">
        </video>
    @endif

    <div class="hero-template-2__inner">
        <div class="hero-template-2__content">
            @if (! empty($content['title']))
                @include('partials.blocks._heading', ['title' => $content['title'], 'tag' => $settings['heading_tag']])
            @endif
            @if (! empty($content['lead']))
                <p class="hero-template-2__description">{{ $content['lead'] }}</p>
            @elseif (! empty($content['description']))
                <p class="hero-template-2__description">{{ $content['description'] }}</p>
            @endif

            @if ($selectorItems->isNotEmpty())
                <div class="hero-template-2__selector" data-hero-template-2>
                    <label class="sr-only" for="{{ $selectId }}">{{ $selectorPlaceholder ?? 'من به دنبال...' }}</label>
                    <select id="{{ $selectId }}" data-hero-template-2-select>
                        <option value="">{{ $selectorPlaceholder ?? 'من به دنبال ...' }}</option>
                        @foreach ($selectorItems as $item)<option value="{{ $item['url'] }}">{{ $item['label'] }}</option>@endforeach
                    </select>
                    <a class="button hero-template-2__button" href="#" aria-disabled="true" data-hero-template-2-button>{{ $buttonLabel }}</a>
                </div>
            @elseif (! empty($primaryCta['url']))
                <a class="button hero-template-2__button" href="{{ $primaryCta['url'] }}">{{ $buttonLabel }}</a>
            @endif

            @if (! empty($secondaryCta['label']) && ! empty($secondaryCta['url']))
                <p class="hero-template-2__helper"><a href="{{ $secondaryCta['url'] }}">{{ $secondaryCta['label'] }}</a></p>
            @endif
        </div>
    </div>
</section>
