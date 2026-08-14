@php
    $data = app(\App\CMS\Blocks\Project\ProjectServicesBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $services = $project ? $project->serviceItems((bool) ($context['publicServicesEnabled'] ?? true)) : collect();
@endphp

@if ($project && $services->isNotEmpty())
    <section class="content-block project-section">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <ul class="project-list">
            @foreach ($services as $service)
                <li>
                    @if (filled($service['url']))
                        <a href="{{ $service['url'] }}">{{ $service['label'] }}</a>
                    @else
                        {{ $service['label'] }}
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@elseif (! $project && app()->hasDebugModeEnabled())
    <p class="empty-state">Project Services requires a project single context.</p>
@endif
