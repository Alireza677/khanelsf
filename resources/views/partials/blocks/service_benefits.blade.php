@php
    $data = app(\App\CMS\Blocks\Service\ServiceBenefitsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $benefits = collect(data_get($context, 'content.benefits', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? null))
        ->values();
@endphp

@if ($benefits->isNotEmpty())
    <section class="content-block service-section service-benefits" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="service-grid service-grid--{{ $data['settings']['columns'] }} service-grid--{{ $data['settings']['variant'] }}">
            @foreach ($benefits as $benefit)
                <article class="service-card">
                    @if ($data['settings']['show_icons'] && filled($benefit['icon'] ?? null))
                        <span class="service-card__icon" aria-hidden="true">{{ $benefit['icon'] }}</span>
                    @endif
                    <h3>{{ $benefit['title'] }}</h3>
                    @if (filled($benefit['description'] ?? null))
                        <p>{{ $benefit['description'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
