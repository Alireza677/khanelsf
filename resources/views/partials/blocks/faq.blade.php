@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
@endphp

<section @class([
    'content-block',
    "content-block--{$background}" => $background !== 'default',
    "content-block--align-{$alignment}",
])>
    <div class="block-heading">
        @if (! empty($data['eyebrow']))
            <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
        @endif

        @if (! empty($data['section_title']))
            <h2>{{ $data['section_title'] }}</h2>
        @endif
    </div>

    <div class="block-stack">
        @foreach (collect($data['items'] ?? [])->filter() as $item)
            <details class="block-faq">
                @if (! empty($item['question']))
                    <summary>{{ $item['question'] }}</summary>
                @endif

                @if (! empty($item['answer']))
                    <p>{{ $item['answer'] }}</p>
                @endif
            </details>
        @endforeach
    </div>
</section>
