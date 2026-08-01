@php
    $data = app(\App\CMS\Blocks\Project\ProjectGalleryBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $images = $project ? $project->galleryImages() : collect();
    $lightbox = $data['settings']['lightbox'];
@endphp

@if ($project && $images->isNotEmpty())
    <section class="content-block project-section">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="block-gallery">
            @foreach ($images as $image)
                @if ($lightbox)
                    <button class="gallery-lightbox-trigger" type="button" data-gallery-lightbox-src="{{ $image->getUrl() }}" data-gallery-lightbox-alt="{{ $image->name }}">
                        <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}">
                    </button>
                @else
                    <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}">
                @endif
            @endforeach
        </div>
    </section>
@elseif ($project && app()->hasDebugModeEnabled())
    <p class="empty-state">Project Gallery has no media in the gallery collection.</p>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Project Gallery requires a project single context.</p>
@endif
