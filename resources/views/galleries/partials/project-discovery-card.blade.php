@php
    $imageRatio = in_array($imageRatio ?? 'landscape', ['landscape', 'square', 'portrait'], true) ? $imageRatio : 'landscape';
    $showCategory = $showCategory ?? true;
    $showDiscoveryTerms = $showDiscoveryTerms ?? true;
@endphp
<article class="gallery-discovery-card">
    <a class="gallery-discovery-card__image gallery-discovery-card__image--{{ $imageRatio }}" href="{{ route('projects.show', $project->slug) }}" aria-label="{{ $project->title }}">
        @if ($project->coverImageUrl())
            <img src="{{ $project->coverImageUrl() }}" alt="{{ $project->title }}" loading="lazy">
        @else
            <span class="gallery-discovery-card__empty">{{ $project->title }}</span>
        @endif
    </a>

    <div class="gallery-discovery-card__body">
        <h2><a href="{{ route('projects.show', $project->slug) }}">{{ $project->title }}</a></h2>

        @if (($showCategory && $project->category) || ($showDiscoveryTerms && $project->discoveryTerms->isNotEmpty()))
            <div class="gallery-discovery-card__meta">
                @if ($showCategory && $project->category)
                    <span>{{ $project->category->name }}</span>
                @endif

                @if ($showDiscoveryTerms)
                    @foreach ($project->discoveryTerms->take(3) as $term)
                        <span>{{ $term->name }}</span>
                    @endforeach
                @endif
            </div>
        @endif
    </div>
</article>
