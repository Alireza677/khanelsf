@php
    $model = $context['model'] ?? null;
    $content = $model?->content ?? null;
@endphp

@if ($content)
    <section class="content-block project-section">
        {!! $content !!}
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Template Single Content has no content for this context.</p>
@endif
