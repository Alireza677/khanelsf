@php($eyebrow = $collectionEyebrow ?? $collection->eyebrow)

@if (filled($eyebrow) || filled($collection->title) || filled($collection->description))
    <header class="shared-collection__header">
        @if (filled($eyebrow)) <p class="shared-collection__eyebrow">{{ $eyebrow }}</p> @endif
        @if (filled($collection->title)) <h1 id="shared-collection-title">{{ $collection->title }}</h1> @endif
        @if (filled($collection->description)) <p class="shared-collection__description">{{ $collection->description }}</p> @endif
    </header>
@endif
