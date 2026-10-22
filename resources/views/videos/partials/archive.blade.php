@php($collection = $archive->collection)
<section class="shared-collection" dir="rtl" aria-labelledby="shared-collection-title">
    @include('partials.presentations.collection.header', ['collection' => $collection])
    @if ($collection->items !== [])
        <div class="block-feature-grid">
            <div class="block-feature-grid__inner">
                @include('partials.blocks.feature-grid.cards', ['grid' => $archive->grid])
            </div>
        </div>
    @else
        @include('partials.presentations.collection.empty', ['collection' => $collection])
    @endif
    @include('partials.presentations.collection.pagination', ['collection' => $collection])
</section>
