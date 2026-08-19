<article @class(['blog-card', $class ?? null])>
    <a class="blog-card__image" href="{{ route('projects.show', $project->slug) }}" aria-label="{{ $project->title }}">
        @if ($project->coverImageUrl())
            <img src="{{ $project->coverImageUrl() }}" alt="{{ $project->title }}" loading="lazy">
        @else
            <span>{{ $project->title }}</span>
        @endif
    </a>

    <div class="blog-card__body">
        <h2><a href="{{ route('projects.show', $project->slug) }}">{{ $project->title }}</a></h2>

        @if ($project->category)
            <a href="{{ route('projects.category', $project->category->slug) }}">{{ $project->category->name }}</a>
        @endif

        @if ($project->excerpt)
            <p>{{ $project->excerpt }}</p>
        @endif

        @if (! empty($showViewLink))
            <a class="blog-card__view-link" href="{{ route('projects.show', $project->slug) }}">
                مشاهده پروژه
            </a>
        @endif
    </div>
</article>
