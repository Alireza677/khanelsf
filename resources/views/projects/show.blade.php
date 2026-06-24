@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <article class="project-detail">
        <header>
            <h1>{{ $project->title }}</h1>

            @if ($project->excerpt)
                <p>{{ $project->excerpt }}</p>
            @endif
        </header>

        @if ($project->featuredImageUrl())
            <img class="project-detail__image" src="{{ $project->featuredImageUrl() }}" alt="{{ $project->title }}">
        @endif

        <dl class="project-meta">
            @if ($project->category)
                <div>
                    <dt>Category</dt>
                    <dd><a href="{{ route('projects.category', $project->category->slug) }}">{{ $project->category->name }}</a></dd>
                </div>
            @endif

            @if ($project->client_name)
                <div>
                    <dt>Client</dt>
                    <dd>{{ $project->client_name }}</dd>
                </div>
            @endif

            @if ($project->location)
                <div>
                    <dt>Location</dt>
                    <dd>{{ $project->location }}</dd>
                </div>
            @endif

            @if ($project->project_date)
                <div>
                    <dt>Date</dt>
                    <dd>{{ $project->project_date->toFormattedDateString() }}</dd>
                </div>
            @endif
        </dl>

        @if (collect($project->services)->isNotEmpty())
            <section class="project-section">
                <h2>Services</h2>
                <ul class="project-list">
                    @foreach ($project->services as $service)
                        <li>{{ $service['name'] ?? $service }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (collect($project->attributes)->isNotEmpty())
            <section class="project-section">
                <h2>Project Details</h2>
                <dl class="project-meta">
                    @foreach ($project->attributes as $attribute)
                        @if (! empty($attribute['label']) && ! empty($attribute['value']))
                            <div>
                                <dt>{{ $attribute['label'] }}</dt>
                                <dd>{{ $attribute['value'] }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($project->external_url)
            <p><a class="button" href="{{ $project->external_url }}" target="_blank" rel="noopener noreferrer">Visit Project</a></p>
        @endif

        @if ($project->content)
            <section class="project-section">
                {!! $project->content !!}
            </section>
        @endif

        <section class="project-section">
            <h2>Gallery</h2>

            @if ($project->galleryImages()->isNotEmpty())
                <div class="block-gallery">
                    @foreach ($project->galleryImages() as $image)
                        <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}">
                    @endforeach
                </div>
            @else
                <p class="empty-state">No gallery images have been added yet.</p>
            @endif
        </section>
    </article>

    @if (($projectGalleries ?? collect())->isNotEmpty())
        <section>
            <div class="section-heading">
                <h2>Project Gallery</h2>
            </div>

            <div class="latest-posts">
                @foreach ($projectGalleries as $projectGallery)
                    @include('galleries.partials.card', ['gallery' => $projectGallery])
                @endforeach
            </div>
        </section>
    @endif

    @if (($relatedProjects ?? collect())->isNotEmpty())
        <section>
            <div class="section-heading">
                <h2>Related Projects</h2>
            </div>

            <div class="latest-posts">
                @foreach ($relatedProjects as $relatedProject)
                    @include('projects.partials.card', ['project' => $relatedProject])
                @endforeach
            </div>
        </section>
    @endif
@endsection
