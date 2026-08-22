@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $items = collect($data['items'] ?? [])->filter(fn ($item): bool => is_array($item) && (filled($item['quote'] ?? null) || filled($item['name'] ?? null)))->values();
    $ctaLabel = is_string($data['cta_label'] ?? null) ? trim($data['cta_label']) : '';
    $ctaPresentation = null;

    if ($ctaLabel !== '' && is_array($data['cta_action'] ?? null)) {
        $resolutionContext = new \App\CMS\Actions\Data\ResolutionContext(
            (! empty($isPreview) || ! empty($context['preview']))
                ? \App\CMS\Actions\Enums\ResolutionMode::Preview
                : \App\CMS\Actions\Enums\ResolutionMode::Production,
        );
        $resolvedAction = app(\App\CMS\Actions\Resolution\RuntimeActionResolver::class)->resolve(
            \App\CMS\Actions\Data\ActionDestination::fromArray($data['cta_action']),
            $resolutionContext,
        );
        $ctaPresentation = app(\App\CMS\Actions\Presentation\ActionPresentation::class)->present($resolvedAction, [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? request()->getRequestUri(),
            'block_id' => $data['block_id'] ?? null,
        ]);
    }
@endphp

@if ($items->isNotEmpty())
    <section
        @class(['content-block', 'testimonial-slider', "content-block--{$background}" => $background !== 'default'])
        dir="rtl"
        data-testimonial-slider
        tabindex="0"
        role="region"
        aria-roledescription="carousel"
        aria-label="{{ $data['section_title'] ?? 'نظرات مشتریان' }}"
    >
        <div class="testimonial-slider__inner">
            @if (! empty($data['eyebrow']))
                <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
            @endif
            @if (! empty($data['section_title']))
                @include('partials.blocks._heading', ['title' => $data['section_title'], 'tag' => $data['heading_tag'] ?? 'h2'])
            @endif

            <div class="testimonial-slider__stage">
                @if ($items->count() > 1)
                    <button type="button" class="testimonial-slider__arrow testimonial-slider__arrow--previous" aria-label="نظر قبلی" data-testimonial-previous><i class="icon-arrow-left-2" aria-hidden="true"></i></button>
                @endif

                <div class="testimonial-slider__slides" data-testimonial-slides aria-live="polite">
                    @foreach ($items as $index => $item)
                        <figure @class(['testimonial-slider__slide', 'is-active' => $index === 0]) data-testimonial-slide data-index="{{ $index }}" aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                            @if (! empty($item['quote']))
                                <blockquote>@include('partials.blocks._rich_text', ['content' => $item['quote'], 'class' => 'testimonial-slider__quote'])</blockquote>
                            @endif
                            <figcaption>
                                @if (! empty($item['avatar']))
                                    <img class="testimonial-slider__avatar" src="{{ $item['avatar'] }}" alt="" loading="lazy">
                                @endif
                                @if (! empty($item['name'])) <strong>{{ $item['name'] }}</strong> @endif
                                @if (! empty($item['role'])) <span>{{ $item['role'] }}</span> @endif
                            </figcaption>
                        </figure>
                    @endforeach
                </div>

                @if ($items->count() > 1)
                    <button type="button" class="testimonial-slider__arrow testimonial-slider__arrow--next" aria-label="نظر بعدی" data-testimonial-next><i class="icon-arrow-right-3" aria-hidden="true"></i></button>
                @endif
            </div>

            @if ($items->count() > 1)
                <div class="testimonial-slider__indicators" role="group" aria-label="انتخاب نظر">
                    @foreach ($items as $index => $item)
                        <button type="button" @class(['testimonial-slider__indicator', 'is-active' => $index === 0]) data-testimonial-indicator="{{ $index }}" aria-label="نمایش نظر {{ $index + 1 }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}"></button>
                    @endforeach
                </div>
            @endif

            @if ($ctaLabel !== '' && is_array($ctaPresentation))
                <div class="testimonial-slider__cta">
                    @include('partials.actions.render', ['label' => $ctaLabel, 'presentation' => $ctaPresentation, 'class' => 'button testimonial-slider__cta-button'])
                </div>
            @endif
        </div>
    </section>

    @once
        <script>
            (() => {
                const initialize = () => document.querySelectorAll('[data-testimonial-slider]').forEach((root) => {
                    if (root.dataset.testimonialSliderInitialized === 'true') return

                    root.dataset.testimonialSliderInitialized = 'true'
                    const slides = Array.from(root.querySelectorAll('[data-testimonial-slide]'))
                    const indicators = Array.from(root.querySelectorAll('[data-testimonial-indicator]'))
                    let activeIndex = 0
                    let touchStartX = null

                    const show = (requestedIndex) => {
                        if (slides.length === 0) return
                        activeIndex = (requestedIndex + slides.length) % slides.length
                        slides.forEach((slide, index) => {
                            const active = index === activeIndex
                            slide.classList.toggle('is-active', active)
                            slide.setAttribute('aria-hidden', active ? 'false' : 'true')
                        })
                        indicators.forEach((indicator, index) => {
                            const active = index === activeIndex
                            indicator.classList.toggle('is-active', active)
                            indicator.setAttribute('aria-current', active ? 'true' : 'false')
                        })
                    }
                    const previous = () => show(activeIndex - 1)
                    const next = () => show(activeIndex + 1)

                    root.querySelector('[data-testimonial-previous]')?.addEventListener('click', previous)
                    root.querySelector('[data-testimonial-next]')?.addEventListener('click', next)
                    indicators.forEach((indicator) => indicator.addEventListener('click', () => show(Number(indicator.dataset.testimonialIndicator))))
                    root.addEventListener('keydown', (event) => {
                        if (event.key === 'ArrowLeft') {
                            event.preventDefault()
                            previous()
                        } else if (event.key === 'ArrowRight') {
                            event.preventDefault()
                            next()
                        }
                    })
                    root.addEventListener('touchstart', (event) => {
                        touchStartX = event.changedTouches[0]?.clientX ?? null
                    }, { passive: true })
                    root.addEventListener('touchend', (event) => {
                        if (touchStartX === null) return
                        const delta = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX
                        touchStartX = null
                        if (Math.abs(delta) < 45) return
                        delta < 0 ? next() : previous()
                    }, { passive: true })
                    show(0)
                })

                document.readyState === 'loading'
                    ? document.addEventListener('DOMContentLoaded', initialize, { once: true })
                    : initialize()
            })()
        </script>
    @endonce
@elseif (! empty($isPreview) || ! empty($context['preview']))
    <section class="content-block testimonial-slider testimonial-slider--empty">برای نمایش اسلایدر، حداقل یک نظر ثبت کنید.</section>
@endif
