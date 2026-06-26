@php
    $model = $context['model'] ?? null;
    $type = $context['type'] ?? null;
    $images = collect();

    if ($model && in_array($type, ['product', 'project'], true) && method_exists($model, 'galleryImages')) {
        $images = $model->galleryImages();
    }

    if ($model && $type === 'gallery' && method_exists($model, 'images')) {
        $images = $model->images();
    }
@endphp

@if ($model && $type === 'gallery' && $model->video_url)
    <section class="content-block project-section">
        @include('partials.blocks._heading', ['title' => $data['video_title'] ?? 'Video', 'tag' => $data['video_heading_tag'] ?? 'h2'])

        @if ($model->videoEmbedUrl())
            <div class="gallery-video">
                <iframe src="{{ $model->videoEmbedUrl() }}" title="{{ $model->title }} video" loading="lazy" allowfullscreen></iframe>
            </div>
        @else
            <a class="button" href="{{ $model->video_url }}" target="_blank" rel="noopener noreferrer">Open video</a>
        @endif
    </section>
@endif

@if ($images->isNotEmpty())
    <section class="content-block project-section">
        @include('partials.blocks._heading', ['title' => $data['title'] ?? 'Gallery', 'tag' => $data['heading_tag'] ?? 'h2'])

        <div class="block-gallery">
            @foreach ($images as $image)
                <button class="gallery-lightbox-trigger" type="button" data-gallery-lightbox-src="{{ $image->getUrl() }}" data-gallery-lightbox-alt="{{ $image->name }}">
                    <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}">
                </button>
            @endforeach
        </div>
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Template Single Gallery has no images for this context.</p>
@endif
