@php($presentationVariant = $collectionVariant ?? $collection->variant)
<div class="shared-collection__grid shared-collection__grid--{{ $collectionColumns ?? $collection->columns }}">
    @foreach ($collection->items as $item)
        @include('partials.presentations.collection.card', ['item' => $item, 'collectionVariant' => $presentationVariant])
    @endforeach
</div>
