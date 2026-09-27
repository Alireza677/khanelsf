@php
    $presentationVariant = $collectionVariant ?? $collection->variant;
    $itemCount = count($collection->items);
    // Explicit boundaries keep balanced CSS columns from leaving the fourth column empty.
    $desktopColumnStarts = array_map(
        fn (int $column): int => $column * intdiv($itemCount, 4) + min($column, $itemCount % 4),
        [1, 2, 3],
    );
    $tabletColumnStart = (int) ceil($itemCount / 2);
@endphp
<div class="shared-collection__grid shared-collection__grid--{{ $collectionColumns ?? $collection->columns }}">
    @foreach ($collection->items as $item)
        @include('partials.presentations.collection.card', [
            'item' => $item,
            'collectionVariant' => $presentationVariant,
            'collectionDesktopColumnStart' => $loop->index > 0 && in_array($loop->index, $desktopColumnStarts, true),
            'collectionTabletColumnStart' => $loop->index > 0 && $loop->index === $tabletColumnStart,
        ])
    @endforeach
</div>
