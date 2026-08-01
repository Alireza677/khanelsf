@php
    $data = app(\App\CMS\Blocks\Service\ServiceDeliverablesBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $deliverables = collect(data_get($context, 'content.deliverables', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? null))
        ->values();
@endphp

@if ($deliverables->isNotEmpty())
    <section class="content-block service-section service-deliverables" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <ul class="service-grid service-grid--{{ $data['settings']['columns'] }} service-grid--{{ $data['settings']['style'] }}">
            @foreach ($deliverables as $item)
                <li class="service-card">
                    <h3>{{ $item['title'] }}</h3>
                    @if (filled($item['description'] ?? null))
                        <p>{{ $item['description'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
