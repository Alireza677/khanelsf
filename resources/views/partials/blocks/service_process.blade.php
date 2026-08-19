@php
    $data = app(\App\CMS\Blocks\Service\ServiceProcessBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $process = collect(data_get($context, 'content.process', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? null))
        ->values();
@endphp

@if ($process->isNotEmpty())
    <section class="content-block service-section service-process service-process--{{ $data['settings']['layout'] }} service-process--{{ $data['settings']['variant'] }}" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <ol class="service-process__list">
            @foreach ($process as $index => $item)
                <li>
                    @if ($data['settings']['show_steps'])
                        <span class="service-process__step">{{ $index + 1 }}</span>
                    @endif
                    <div>
                        <h3>{{ $item['title'] }}</h3>
                        @if (filled($item['description'] ?? null))
                            <p>{{ $item['description'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
