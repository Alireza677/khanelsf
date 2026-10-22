@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
@endphp

<section @class([
    'content-block',
    'block-faq-section',
    "content-block--{$background}" => $background !== 'default',
    "content-block--align-{$alignment}",
])>
    <div class="block-faq-section__inner">
        @if (filled($data['eyebrow'] ?? null) || filled($data['section_title'] ?? null) || filled($data['section_description'] ?? null))
            <div class="block-heading block-faq-section__heading">
                @if (! empty($data['eyebrow']))
                    <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
                @endif

                @if (! empty($data['section_title']))
                    @include('partials.blocks._heading', ['title' => $data['section_title'], 'tag' => $data['heading_tag'] ?? 'h2'])
                @endif

                @include('partials.blocks._rich_text', ['content' => $data['section_description'] ?? null])
            </div>
        @endif

        <div class="block-stack block-faq-section__items">
            @foreach (collect($data['items'] ?? [])->filter() as $item)
                <details class="block-faq">
                    <summary class="block-faq__question">
                        <span class="block-faq__label">{{ filled($item['question'] ?? null) ? $item['question'] : __('Question') }}</span>
                        <span class="block-faq__icon" aria-hidden="true"></span>
                    </summary>

                    <div class="block-faq__answer">
                        <div class="block-faq__answer-content">
                            @include('partials.blocks._rich_text', ['content' => $item['answer'] ?? null])
                        </div>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
