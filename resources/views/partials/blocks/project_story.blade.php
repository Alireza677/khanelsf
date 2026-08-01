@php
    $data = app(\App\CMS\Blocks\Project\ProjectStoryBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $sections = $project ? collect([
        'challenge' => $project->challenge,
        'solution' => $project->solution,
        'results_summary' => $project->results_summary,
        'client_quote' => $project->client_quote,
    ])->filter(fn ($value, string $key): bool => $data['settings']["show_{$key}"] && filled($value)) : collect();
    $legacyContent = $project && $sections->isEmpty() && filled($project->content)
        ? $project->content
        : null;
@endphp

@if ($project && ($sections->isNotEmpty() || $legacyContent))
    <section class="content-block project-section project-story">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        @foreach ($sections as $key => $value)
            @if ($key === 'client_quote')
                <blockquote class="project-story__quote">
                    <h3>{{ $data['content']['headings'][$key] }}</h3>
                    <div>{!! nl2br(e($value)) !!}</div>
                </blockquote>
            @else
                <article class="project-story__section">
                    <h3>{{ $data['content']['headings'][$key] }}</h3>
                    <div>{!! nl2br(e($value)) !!}</div>
                </article>
            @endif
        @endforeach

        @if ($legacyContent)
            <div class="project-story__legacy-content">
                {!! $legacyContent !!}
            </div>
        @endif
    </section>
@elseif (! $project && app()->hasDebugModeEnabled())
    <p class="empty-state">Project Story requires a project single context.</p>
@endif
