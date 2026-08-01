@php
    $data = app(\App\CMS\Blocks\Service\ServiceProjectsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $projects = collect($context['projects'] ?? []);
@endphp

@if ($projects->isNotEmpty())
    <section class="content-block service-section service-projects" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="latest-posts service-grid--{{ $data['settings']['columns'] }}">
            @foreach ($projects as $project)
                @include('projects.partials.card', ['project' => $project])
            @endforeach
        </div>
    </section>
@endif
