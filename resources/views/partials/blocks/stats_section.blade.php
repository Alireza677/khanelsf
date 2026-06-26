@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
    $items = collect($data['items'] ?? [])->filter(fn ($item) => ! empty($item['value']) || ! empty($item['label']));
    $innerWidth = max(20, min((int) ($data['inner_width_percent'] ?? 70), 100));
@endphp

@if ($items->isNotEmpty() || ! empty($data['section_title']) || ! empty($data['section_description']))
    <section @class([
        'content-block',
        'block-stats-section',
        "content-block--{$background}" => $background !== 'default',
        "content-block--align-{$alignment}",
    ]) style="--stats-inner-width: {{ $innerWidth }}%;">
        <div class="stats-section__inner">
            <div class="block-heading">
                @if (! empty($data['eyebrow']))
                    <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
                @endif

                @if (! empty($data['section_title']))
                    @include('partials.blocks._heading', ['title' => $data['section_title'], 'tag' => $data['heading_tag'] ?? 'h2'])
                @endif

                @if (! empty($data['section_description']))
                    <p>{{ $data['section_description'] }}</p>
                @endif
            </div>

            @if ($items->isNotEmpty())
                <div class="stats-grid">
                    @foreach ($items as $item)
                        @php
                            $rawValue = trim((string) ($item['value'] ?? ''));
                            preg_match('/^(.*?)([0-9][0-9,.]*)(.*)$/', $rawValue, $valueParts);
                            $counterPrefix = $valueParts[1] ?? '';
                            $counterNumber = $valueParts[2] ?? '';
                            $counterSuffix = $valueParts[3] ?? '';
                            $counterTarget = (int) str_replace([',', '.'], '', $counterNumber);
                        @endphp

                        <article class="stats-item">
                            @if (! empty($item['value']))
                                <strong
                                    data-stats-counter
                                    data-counter-target="{{ $counterTarget }}"
                                    data-counter-prefix="{{ $counterPrefix }}"
                                    data-counter-suffix="{{ $counterSuffix }}"
                                    data-counter-formatted="{{ $rawValue }}"
                                >{{ $item['value'] }}</strong>
                            @endif

                            @if (! empty($item['label']))
                                <span>{{ $item['label'] }}</span>
                            @endif

                            @if (! empty($item['description']))
                                <small>{{ $item['description'] }}</small>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
