@if (filled($content ?? null))
    <div @class(['block-rich-text', $class ?? null])>{{ \App\Support\RichText::render($content) }}</div>
@endif
