@php
    $data = app(\App\CMS\Blocks\Project\ProjectMetricsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $metrics = $project ? $project->metrics : collect();
@endphp

@if ($project && $metrics->isNotEmpty())
    <section class="content-block project-section">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="stats-grid">
            @foreach ($metrics as $metric)
                <article class="stats-item">
                    @if ($metric->icon)
                        <span class="stats-item__icon">
                            @include('partials.blocks._icon', ['icon' => $metric->icon])
                        </span>
                    @endif
                    <strong>{{ $metric->prefix }}{{ $metric->value }}{{ $metric->suffix }}</strong>
                    <span>{{ $metric->label }}</span>
                    @if ($metric->description)
                        <p class="stats-item__description">{{ $metric->description }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@elseif (! $project && app()->hasDebugModeEnabled())
    <p class="empty-state">Project Metrics requires a project single context.</p>
@endif
