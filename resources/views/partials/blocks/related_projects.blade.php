@php
    $data = app(\App\CMS\Blocks\Project\RelatedProjectsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $related = $project
        ? collect($context['related'] ?? [])->take($data['settings']['limit'])
        : collect();
@endphp

@if ($project && $related->isNotEmpty())
    <section class="content-block project-section">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="latest-posts">
            @foreach ($related as $relatedProject)
                @include('projects.partials.card', ['project' => $relatedProject])
            @endforeach
        </div>
    </section>
@elseif ($project && app()->hasDebugModeEnabled())
    <p class="empty-state">Related Projects has no items in the template context.</p>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Related Projects requires a project single context.</p>
@endif
