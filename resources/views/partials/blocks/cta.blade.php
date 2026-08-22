@php
    $cta = app(\App\CMS\Blocks\CTA\CTADataNormalizer::class)->normalize($data);
    $template = $cta['template'];
    $content = $cta['content'];
    $settings = $cta['settings'];
    $primaryCta = $content['primary_cta'];
    $secondaryCta = $content['secondary_cta'];
    $actionContext = [
        'page_id' => $context['page_id'] ?? null,
        'page_url' => $context['page_url'] ?? request()->getRequestUri(),
        'block_id' => $cta['block_id'],
    ];
    $resolutionContext = new \App\CMS\Actions\Data\ResolutionContext(
        (! empty($isPreview) || ! empty($context['preview']))
            ? \App\CMS\Actions\Enums\ResolutionMode::Preview
            : \App\CMS\Actions\Enums\ResolutionMode::Production,
    );
    $actionResolver = app(\App\CMS\Actions\Contracts\ActionResolver::class);
    $actionPresentation = app(\App\CMS\Actions\Presentation\ActionPresentation::class);
    $present = fn (array $button): ?array => $actionPresentation->present(
        $actionResolver->resolve(
            \App\CMS\Actions\Data\ActionDestination::fromArray(
                is_array($button['action'] ?? null) ? $button['action'] : [],
            ),
            $resolutionContext,
        ),
        $actionContext,
    );
    $primaryPresentation = $present($primaryCta);
    $secondaryPresentation = $present($secondaryCta);
    $backgroundImage = $content['media']['url'];
    $backgroundVariables = \App\Support\BlockImageStyle::normalizedBackgroundVariables($settings['media']);
    $imageStyle = filled($backgroundImage)
        ? "background-image: linear-gradient(90deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,.58) 42%, rgba(255,255,255,.98) 74%), url('".e($backgroundImage)."');"
        : null;
    $contentWidth = filled($settings['content_width'])
        ? max(240, min(1400, (int) $settings['content_width']))
        : null;
    $hasVisibleContent = filled($content['eyebrow'])
        || filled($content['title'])
        || filled($content['description'])
        || filled($primaryCta['label'])
        || filled($secondaryCta['label'])
        || filled($backgroundImage);
@endphp

@include('partials.blocks._image_control_styles')

@if ($hasVisibleContent && $template === 'image')
    <section
        class="content-block block-cta-image block-configured-background"
        @if ($imageStyle || $backgroundVariables) style="{!! trim($imageStyle.' '.$backgroundVariables, ' ;') !!}" @endif
    >
        <div class="block-cta-image__inner">
            <div class="block-cta-image__content" @if ($contentWidth) style="max-width: {{ $contentWidth }}px" @endif>
                @if (! empty($content['title']))
                    @include('partials.blocks._heading', ['title' => $content['title'], 'tag' => $settings['heading_tag']])
                @endif

                @include('partials.blocks._rich_text', ['content' => $content['description'] ?? null])

                @if (! empty($primaryCta['label']) || ! empty($secondaryCta['label']))
                    <div class="block-cta-image__actions">
                        @include('partials.actions.render', [
                            'label' => $primaryCta['label'],
                            'class' => 'button block-cta-image__primary',
                            'presentation' => $primaryPresentation,
                        ])
                        @include('partials.actions.render', [
                            'label' => $secondaryCta['label'],
                            'class' => 'button block-cta-image__secondary',
                            'presentation' => $secondaryPresentation,
                        ])
                    </div>
                @endif
            </div>
        </div>
    </section>
@elseif ($hasVisibleContent)
    <section @class([
        'content-block',
        'block-cta',
        "content-block--{$settings['background']}" => $settings['background'] !== 'default' && $settings['background'] !== 'dark',
        "content-block--align-{$settings['alignment']}",
    ])>
        <div>
            @if (! empty($content['eyebrow']))
                <p class="block-eyebrow">{{ $content['eyebrow'] }}</p>
            @endif

            @if (! empty($content['title']))
                @include('partials.blocks._heading', ['title' => $content['title'], 'tag' => $settings['heading_tag']])
            @endif

            @include('partials.blocks._rich_text', ['content' => $content['description'] ?? null])
        </div>

        @if (! empty($primaryCta['label']) || ! empty($secondaryCta['label']))
            <div class="block-cta__actions">
                @include('partials.actions.render', [
                    'label' => $primaryCta['label'],
                    'class' => 'button',
                    'presentation' => $primaryPresentation,
                ])
                @include('partials.actions.render', [
                    'label' => $secondaryCta['label'],
                    'class' => 'button button--outline',
                    'presentation' => $secondaryPresentation,
                ])
            </div>
        @endif
    </section>
@endif
