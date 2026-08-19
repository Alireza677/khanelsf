<article @class(['shared-collection-card', 'shared-collection-card--masonry' => ($collectionVariant ?? null) === 'masonry_gallery'])>
    @if (($collectionVariant ?? null) === 'masonry_gallery')
        @if ($item->action)
            <a class="shared-collection-card__tile-link" href="{{ $item->action->href }}" @if($item->action->target) target="{{ $item->action->target }}" @endif @if($item->action->rel) rel="{{ $item->action->rel }}" @endif aria-label="{{ $item->title }} — {{ $collectionActionLabel ?? $item->action->label }}">
        @else
            <div class="shared-collection-card__tile-link">
        @endif
            <div class="shared-collection-card__media">
                @if (($collectionShowImage ?? true) && $item->image)
                    <img src="{{ $item->image->url }}" alt="{{ $item->image->alt }}" loading="lazy">
                @elseif (($collectionShowIcon ?? true) && filled($item->icon))
                    <span class="shared-collection-card__icon" aria-hidden="true">@include('partials.blocks._icon', ['icon' => $item->icon])</span>
                @else
                    <span class="shared-collection-card__image-fallback" aria-hidden="true"></span>
                @endif
            </div>
            <div class="shared-collection-card__overlay">
                @if (($collectionShowBadges ?? true) && $item->badges !== [])
                    <div class="shared-collection-card__badges">@foreach ($item->badges as $badge)<span>{{ $badge }}</span>@endforeach</div>
                @endif
                <h2>{{ $item->title }}</h2>
                @if (($collectionShowExcerpt ?? true) && filled($item->excerpt)) <p class="shared-collection-card__excerpt">{{ $item->excerpt }}</p> @endif
                @if (($collectionShowMeta ?? true) && $item->metaItems !== [])
                    <dl class="shared-collection-card__meta">
                        @foreach ($item->metaItems as $meta)
                            <div>@if (filled($meta->label)) <dt>{{ $meta->label }}</dt> @endif<dd>{{ $meta->value }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                @if (($collectionShowAction ?? true) && $item->action)
                    <span class="shared-collection-card__action">{{ $collectionActionLabel ?? $item->action->label }}</span>
                @endif
            </div>
        @if ($item->action) </a> @else </div> @endif
    @else
    @if (($collectionShowImage ?? true) && $item->image || ($collectionShowIcon ?? true) && filled($item->icon))
        @if ($item->action) <a class="shared-collection-card__media" href="{{ $item->action->href }}" aria-label="{{ $item->title }}"> @else <div class="shared-collection-card__media"> @endif
            @if (($collectionShowImage ?? true) && $item->image)
                <img src="{{ $item->image->url }}" alt="{{ $item->image->alt }}" loading="lazy">
            @elseif (filled($item->icon))
                <span class="shared-collection-card__icon" aria-hidden="true">@include('partials.blocks._icon', ['icon' => $item->icon])</span>
            @endif
        @if ($item->action) </a> @else </div> @endif
    @endif

    <div class="shared-collection-card__body">
        @if (filled($item->eyebrow)) <p class="shared-collection-card__eyebrow">{{ $item->eyebrow }}</p> @endif
        @if (($collectionShowBadges ?? true) && $item->badges !== [])
            <div class="shared-collection-card__badges">@foreach ($item->badges as $badge)<span>{{ $badge }}</span>@endforeach</div>
        @endif
        <h2>
            @if ($item->action)<a href="{{ $item->action->href }}">{{ $item->title }}</a>@else{{ $item->title }}@endif
        </h2>
        @if (($collectionShowExcerpt ?? true) && filled($item->excerpt)) <p class="shared-collection-card__excerpt">{{ $item->excerpt }}</p> @endif
        @if (($collectionShowMeta ?? true) && $item->metaItems !== [])
            <dl class="shared-collection-card__meta">
                @foreach ($item->metaItems as $meta)
                    <div>
                        @if (filled($meta->icon)) <span aria-hidden="true">@include('partials.blocks._icon', ['icon' => $meta->icon])</span> @endif
                        @if (filled($meta->label)) <dt>{{ $meta->label }}</dt> @endif
                        <dd>{{ $meta->value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
        @if (($collectionShowAction ?? true) && $item->action)
            <a class="shared-collection-card__action" href="{{ $item->action->href }}" @if($item->action->target) target="{{ $item->action->target }}" @endif @if($item->action->rel) rel="{{ $item->action->rel }}" @endif>{{ $collectionActionLabel ?? $item->action->label }}</a>
        @endif
    </div>
    @endif
</article>
