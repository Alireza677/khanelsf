@php
    $blocks = $blocks ?? [];
    $context = $context ?? [];
@endphp

@foreach (collect($blocks)->filter() as $block)
    @php
        $type = $block['type'] ?? null;
        $data = $block['data'] ?? $block;
        $view = $type ? "partials.blocks.{$type}" : null;
    @endphp

    @if ($view && view()->exists($view))
        @include($view, ['data' => $data, 'context' => $context])
    @endif
@endforeach
