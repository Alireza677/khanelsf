@php
    $blocks = $blocks ?? [];
    $context = $context ?? [];
    $blockRegistry = app(\App\CMS\Blocks\BlockRegistry::class);
@endphp

@foreach (collect($blocks)->filter() as $block)
    @php
        $type = $block['type'] ?? null;
        $data = $block['data'] ?? $block;
        $legacyView = $type ? "partials.blocks.{$type}" : null;
        $registeredView = $type
            ? $blockRegistry->renderView($type, is_array($data) ? $data : [])
            : null;
        $view = $registeredView && view()->exists($registeredView)
            ? $registeredView
            : $legacyView;
    @endphp

    @if ($view && view()->exists($view))
        @include($view, ['data' => $data, 'context' => $context])
    @endif
@endforeach
