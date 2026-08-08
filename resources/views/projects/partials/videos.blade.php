<section class="project-section">
    <h2>Videos</h2>

    <div class="block-gallery">
        @foreach ($videos as $video)
            <article class="gallery-video-card">
                @if ($video->embedUrl())
                    <div class="gallery-video">
                        <iframe
                            src="{{ $video->embedUrl() }}"
                            title="{{ $video->title ?: $project->title }}"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        ></iframe>
                    </div>
                @else
                    @if ($video->thumbnail_url)
                        <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title ?: $project->title }}" loading="lazy">
                    @endif

                    <a class="button" href="{{ $video->url }}" target="_blank" rel="noopener noreferrer">Open video</a>
                @endif

                @if ($video->title)
                    <h3>{{ $video->title }}</h3>
                @endif

                @if ($video->caption)
                    <p>{{ $video->caption }}</p>
                @endif
            </article>
        @endforeach
    </div>
</section>
