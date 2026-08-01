@if ($variant === 'header')
    <nav class="site-nav" id="site-navigation" aria-label="ناوبری اصلی" data-site-nav>
        @if ($menu?->rootItems?->isNotEmpty())
            <ul>
                @include('components.navigation-items', [
                    'items' => $menu->rootItems,
                    'depth' => 1,
                    'maxDepth' => $maxDepth,
                    'variant' => $variant,
                ])
            </ul>
        @endif
    </nav>
@elseif ($variant === 'footer' && $menu?->rootItems?->isNotEmpty())
    <nav aria-label="Footer navigation">
        <h3>Links</h3>
        <ul class="footer-menu">
            @include('components.navigation-items', [
                'items' => $menu->rootItems,
                'depth' => 1,
                'maxDepth' => $maxDepth,
                'variant' => $variant,
            ])
        </ul>
    </nav>
@endif
