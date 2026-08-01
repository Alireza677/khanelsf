@php
    $data = app(\App\CMS\Blocks\Service\RelatedServicesBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $services = collect($context['relatedServices'] ?? [])->filter();
@endphp

@if ($services->isNotEmpty())
    <section class="content-block service-section related-services" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="service-grid service-grid--{{ $data['settings']['columns'] }}">
            @foreach ($services as $relatedService)
                @php
                    $name = data_get($relatedService, 'name');
                    $slug = data_get($relatedService, 'slug');
                    $url = filled($slug) && \Illuminate\Support\Facades\Route::has('services.show')
                        ? route('services.show', $slug)
                        : null;
                @endphp

                @if (filled($name))
                    <article class="service-card">
                        <h3>
                            @if ($url)
                                <a href="{{ $url }}">{{ $name }}</a>
                            @else
                                {{ $name }}
                            @endif
                        </h3>
                        @if (filled(data_get($relatedService, 'excerpt')))
                            <p>{{ data_get($relatedService, 'excerpt') }}</p>
                        @endif
                    </article>
                @endif
            @endforeach
        </div>
    </section>
@endif
