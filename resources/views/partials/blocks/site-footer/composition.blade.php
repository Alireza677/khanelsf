@if ($footer['composition']['main']['columns'] !== [] || $footer['composition']['bottom']['elements'] !== [])
@php
    // Presentation only: all inputs are already canonical, resolved runtime data.
    $footerTracks = max(1, array_sum(array_map(fn (array $column): int => $column['settings']['span'], $footer['composition']['main']['columns'])));
    $footerDesktopGrid = $footer['settings']['grid']['desktop'] === 'auto'
        ? 'min('.min(8, $footerTracks).', var(--footer-auto-columns))'
        : (string) $footer['settings']['grid']['desktop'];
@endphp
<footer class="site-footer corporate-footer" dir="rtl" data-site-footer data-footer-composition
    data-container="{{ $footer['settings']['container'] }}" data-spacing="{{ $footer['settings']['spacing'] }}"
    data-grid-desktop="{{ $footer['settings']['grid']['desktop'] }}"
    data-grid-tablet="{{ $footer['settings']['grid']['tablet'] }}" data-grid-mobile="{{ $footer['settings']['grid']['mobile'] }}"
    style="--footer-grid-desktop: {{ $footerDesktopGrid }}; --footer-grid-tablet: {{ $footer['settings']['grid']['tablet'] }}; --footer-grid-mobile: {{ $footer['settings']['grid']['mobile'] }};">
    <div class="container corporate-footer__inner">
        @if ($footer['composition']['main']['columns'] !== [])
            <div class="corporate-footer__primary corporate-footer__primary--adaptive">
                @foreach ($footer['composition']['main']['columns'] as $column)
                    <section class="corporate-footer__column" data-footer-column="{{ $column['id'] }}" data-column-span="{{ $column['settings']['span'] }}"
                        style="--footer-column-span: {{ $column['settings']['span'] }};">
                        @if ($column['title'])
                            <h2 class="corporate-footer__heading">{{ $column['title'] }}</h2>
                        @endif
                        @foreach ($column['elements'] as $element)
                            @if ($element['type'] === 'image' && ($column['elements'][$loop->index - 1]['type'] ?? null) !== 'image')
                                <div class="corporate-footer__image-group">
                            @endif
                            @include('partials.blocks.site-footer.element', ['element' => $element, 'heading' => $column['title'] ? 'h3' : 'h2'])
                            @if ($element['type'] === 'image' && ($column['elements'][$loop->index + 1]['type'] ?? null) !== 'image')
                                </div>
                            @endif
                        @endforeach
                    </section>
                @endforeach
            </div>
        @endif
        @if ($footer['composition']['bottom']['elements'] !== [])
            <div class="corporate-footer__bottom">
                @foreach ($footer['composition']['bottom']['elements'] as $element)
                    @include('partials.blocks.site-footer.element', ['element' => $element, 'heading' => 'h2'])
                @endforeach
            </div>
        @endif
    </div>
</footer>
@endif
