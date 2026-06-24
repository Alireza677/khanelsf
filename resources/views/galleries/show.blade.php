@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <article class="project-detail">
        <header>
            <h1>{{ $gallery->title }}</h1>

            @if ($gallery->excerpt)
                <p>{{ $gallery->excerpt }}</p>
            @endif
        </header>

        @if ($gallery->cardImageUrl(null))
            <button class="gallery-lightbox-trigger gallery-hero-image" type="button" data-gallery-lightbox-src="{{ $gallery->cardImageUrl(null) }}" data-gallery-lightbox-alt="{{ $gallery->title }}">
                <img class="project-detail__image" src="{{ $gallery->cardImageUrl(null) }}" alt="{{ $gallery->title }}">
            </button>
        @endif

        <dl class="project-meta">
            <div>
                <dt>Type</dt>
                <dd>{{ ucfirst($gallery->type) }}</dd>
            </div>

            @if ($gallery->category)
                <div>
                    <dt>Category</dt>
                    <dd><a href="{{ route('galleries.category', $gallery->category->slug) }}">{{ $gallery->category->name }}</a></dd>
                </div>
            @endif

            @if ($gallery->project)
                <div>
                    <dt>Related Project</dt>
                    <dd><a href="{{ route('projects.show', $gallery->project->slug) }}">{{ $gallery->project->title }}</a></dd>
                </div>
            @endif
        </dl>

        @if ($gallery->video_url)
            <section class="project-section">
                <h2>Video</h2>

                @if ($gallery->videoEmbedUrl())
                    <div class="gallery-video">
                        <iframe
                            src="{{ $gallery->videoEmbedUrl() }}"
                            title="{{ $gallery->title }} video"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        ></iframe>
                    </div>
                @else
                    <div class="gallery-video-card">
                        @if ($gallery->cardImageUrl(null))
                            <img src="{{ $gallery->cardImageUrl(null) }}" alt="{{ $gallery->title }}">
                        @endif

                        <div>
                            <h3>{{ $gallery->title }}</h3>
                            <p>This video opens on the external video provider.</p>
                            <a class="button" href="{{ $gallery->video_url }}" target="_blank" rel="noopener noreferrer">Open video</a>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="project-section">
            <h2>Images</h2>

            @if ($gallery->images()->isNotEmpty())
                <div class="block-gallery">
                    @foreach ($gallery->images() as $image)
                        <button class="gallery-lightbox-trigger" type="button" data-gallery-lightbox-src="{{ $image->getUrl() }}" data-gallery-lightbox-alt="{{ $image->name }}">
                            <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}">
                        </button>
                    @endforeach
                </div>
            @else
                <p class="empty-state">No gallery images have been added yet.</p>
            @endif
        </section>

        @if ($gallery->content)
            <section class="project-section">
                {!! $gallery->content !!}
            </section>
        @endif
    </article>

    @if (($relatedGalleries ?? collect())->isNotEmpty())
        <section>
            <div class="section-heading">
                <h2>Related Galleries</h2>
            </div>

            <div class="latest-posts">
                @foreach ($relatedGalleries as $relatedGallery)
                    @include('galleries.partials.card', ['gallery' => $relatedGallery])
                @endforeach
            </div>
        </section>
    @endif
@endsection
