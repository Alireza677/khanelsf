@php
    $content = $hero['content'];
    $settings = $hero['settings'];
    $media = $content['media'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $selector = $content['selector'];
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
    $selectorItems = collect($selector['items'] ?? [])->filter(fn ($item) => filled($item['label'] ?? null))->values();
    $selectorPlaceholder = $selector['placeholder'] ?? null;
    $defaultIndex = is_numeric($selector['default_index'] ?? null) ? (int) $selector['default_index'] : null;
    $hasValidDefault = $defaultIndex !== null && is_array(data_get($selectorItems, "{$defaultIndex}.presentation"));
    $buttonLabel = $primaryCta['label'] ?? 'Get Started';
    $selectId = 'hero-template-2-select-'.substr(md5(json_encode($hero)), 0, 10);
@endphp

@include('partials.blocks._image_control_styles')

<section class="content-block hero-template-2{{ $heightClass }} block-configured-background" @if ($sectionStyle) style="{!! $sectionStyle !!}" @endif>
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
            @include('partials.blocks._rich_text', [
                'content' => ! empty($content['lead']) ? $content['lead'] : ($content['description'] ?? null),
                'class' => 'hero-template-2__description',
            ])

            @if ($selectorItems->isNotEmpty())
                <div class="hero-template-2__selector" data-hero-template-2>
                    <label class="sr-only" for="{{ $selectId }}">{{ $selectorPlaceholder ?? 'من به دنبال...' }}</label>
                    <select id="{{ $selectId }}" data-hero-template-2-select>
                        <option value="" @selected(! $hasValidDefault)>{{ $selectorPlaceholder ?? 'من به دنبال ...' }}</option>
                        @foreach ($selectorItems as $item)<option value="{{ $loop->index }}" @selected($hasValidDefault && $defaultIndex === $loop->index)>{{ $item['label'] }}</option>@endforeach
                    </select>
                    <div class="hero-template-2__actions" data-hero-template-2-action-slot data-button-label="{{ $buttonLabel }}">
                        <button class="button hero-template-2__button" type="button" disabled data-hero-template-2-button>{{ $buttonLabel }}</button>
                    </div>
                    @foreach ($selectorItems as $item)
                        @if (is_array($item['presentation'] ?? null))
                            <template data-hero-template-2-action="{{ $loop->index }}">
                                @include('partials.actions.render', ['label' => $buttonLabel, 'class' => 'button hero-template-2__button', 'presentation' => $item['presentation']])
                            </template>
                        @endif
                    @endforeach
                </div>
            @else
                <button class="button hero-template-2__button" type="button" disabled data-hero-template-2-button>{{ $buttonLabel }}</button>
            @endif

            @if (! empty($secondaryCta['label']) && ! empty($secondaryCta['presentation']))
                <div class="hero-template-2__helper">@include('partials.actions.render', ['label' => $secondaryCta['label'], 'class' => '', 'presentation' => $secondaryCta['presentation']])</div>
            @endif
        </div>
    </div>
</section>
