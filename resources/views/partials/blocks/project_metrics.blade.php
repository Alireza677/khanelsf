@php
    $data = app(\App\CMS\Blocks\Project\ProjectMetricsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $metrics = $project ? $project->metrics : collect();
@endphp

@if ($project && $metrics->isNotEmpty())
    <section class="content-block project-section project-achievements">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="project-achievements__grid">
            @foreach ($metrics as $metric)
                <article class="project-achievements__card">
                    <span class="project-achievements__icon" aria-hidden="true">
                        @include('partials.blocks._icon', ['icon' => $metric->icon, 'fallback' => 'heroicon-o-chart-bar'])
                    </span>
                    <div class="project-achievements__measurement">
                        @if (filled($metric->prefix))
                            <bdi class="project-achievements__affix" dir="auto">{{ $metric->prefix }}</bdi>
                        @endif
                        <strong class="project-achievements__value"><bdi dir="auto">{{ $metric->value }}</bdi></strong>
                        @if (filled($metric->suffix))
                            <bdi class="project-achievements__affix" dir="auto">{{ $metric->suffix }}</bdi>
                        @endif
                    </div>
                    <span class="project-achievements__label">{{ $metric->label }}</span>
                    @if ($metric->description)
                        <p class="project-achievements__description">{{ $metric->description }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@elseif (! $project && app()->hasDebugModeEnabled())
    <p class="empty-state">Project Metrics requires a project single context.</p>
@endif
