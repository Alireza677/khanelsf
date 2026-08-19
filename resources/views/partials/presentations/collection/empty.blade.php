@if ($collection->emptyState)
    <div class="shared-collection__empty" role="status">
        @if (filled($collection->emptyState->icon)) <span aria-hidden="true">@include('partials.blocks._icon', ['icon' => $collection->emptyState->icon])</span> @endif
        <p>{{ $collection->emptyState->title }}</p>
        @if (filled($collection->emptyState->description)) <small>{{ $collection->emptyState->description }}</small> @endif
    </div>
@endif
