@php
    $data = app(\App\CMS\Blocks\Project\RelatedProjectsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $related = $project
        ? collect($context['related'] ?? [])->take($data['settings']['limit'])
        : collect();
@endphp

@if ($project && $related->isNotEmpty())
    <section class="content-block project-section related-projects">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="related-projects__grid">
            @foreach ($related as $relatedProject)
                @php($url = $relatedProject->resolveNavigationUrl())
                <article class="related-project-card">
                    @if ($url)<a class="related-project-card__media" href="{{ $url }}" aria-label="{{ $relatedProject->title }}">@else<div class="related-project-card__media">@endif
                        @if ($relatedProject->coverImageUrl())
                            <img src="{{ $relatedProject->coverImageUrl() }}" alt="{{ $relatedProject->title }}" loading="lazy">
                        @else
                            <span aria-hidden="true"></span>
                        @endif
                        @if ($relatedProject->category)<em>{{ $relatedProject->category->name }}</em>@endif
                    @if ($url)</a>@else</div>@endif
                    <div class="related-project-card__body">
                        <h3>@if($url)<a href="{{ $url }}">{{ $relatedProject->title }}</a>@else{{ $relatedProject->title }}@endif</h3>
                        @if (filled($relatedProject->location) || filled($relatedProject->project_type))
                            <p>{{ collect([$relatedProject->location, $relatedProject->project_type])->filter()->implode(' · ') }}</p>
                        @endif
                        @if($url)<a class="related-project-card__action" href="{{ $url }}">مشاهده پروژه</a>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@elseif ($project && app()->hasDebugModeEnabled())
    <p class="empty-state">Related Projects has no items in the template context.</p>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Related Projects requires a project single context.</p>
@endif
