@php
    $data = app(\App\CMS\Blocks\Service\ServiceOverviewBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $overview = data_get($context, 'content.overview');
    $overview = is_scalar($overview) ? trim((string) $overview) : '';
@endphp

@if ($overview !== '')
    <section class="content-block service-section service-overview service-overview--{{ $data['settings']['width'] }}" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="prose">{!! $overview !!}</div>
    </section>
@endif
