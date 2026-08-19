<section class="shared-collection shared-collection--{{ $collection->variant }}" dir="{{ $collection->direction === 'ltr' ? 'ltr' : 'rtl' }}" aria-labelledby="shared-collection-title">
    @include('partials.presentations.collection.header', ['collection' => $collection])

    @if ($collection->items !== [])
        @include('partials.presentations.collection.grid', ['collection' => $collection])
    @else
        @include('partials.presentations.collection.empty', ['collection' => $collection])
    @endif

    @include('partials.presentations.collection.pagination', ['collection' => $collection])
</section>
