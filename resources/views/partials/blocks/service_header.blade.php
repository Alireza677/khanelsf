@php
    $hero = app(\App\CMS\Blocks\Service\ServiceHeaderRuntime::class)->prepare(
        is_array($data ?? null) ? $data : [],
        is_array($context ?? null) ? $context : [],
        ! empty($isPreview) || ! empty($context['preview']),
    );
@endphp

@if (count($context['breadcrumbs'] ?? []) > 1)
    <nav class="content-block archive-breadcrumb service-breadcrumb" aria-label="مسیر صفحه">
        <ol>
            @foreach ($context['breadcrumbs'] as $breadcrumb)
                <li>
                    @if (! $loop->last && filled($breadcrumb['url'] ?? null))
                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                    @else
                        <span aria-current="{{ $loop->last ? 'page' : 'false' }}">{{ $breadcrumb['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif

@include('partials.presentations.hero', ['hero' => $hero])
