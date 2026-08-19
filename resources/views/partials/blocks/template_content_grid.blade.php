@php
    $items = $context['items'] ?? collect();
    $type = $context['type'] ?? null;
    $emptyMessage = $data['empty_message'] ?? $context['emptyMessage'] ?? 'No items found.';
    $collection = $context['collection'] ?? null;
    $columnsDesktop = (int) ($data['columns_desktop'] ?? 3);
    $columnsDesktop = in_array($columnsDesktop, [2, 3, 4], true) ? $columnsDesktop : 3;
    $columnsTablet = (int) ($data['columns_tablet'] ?? 2);
    $columnsTablet = in_array($columnsTablet, [1, 2], true) ? $columnsTablet : 2;
    $imageRatio = in_array($data['image_ratio'] ?? null, ['16:10', '16:9', '4:3', '1:1'], true)
        ? $data['image_ratio']
        : '16:10';
    $cardDensity = in_array($data['card_density'] ?? null, ['compact', 'comfortable'], true)
        ? $data['card_density']
        : 'comfortable';
    $presentationVariant = in_array($data['presentation_variant'] ?? null, ['clean_grid', 'masonry_gallery'], true)
        ? $data['presentation_variant']
        : $collection?->variant;
    $enabled = fn (string $key, bool $default = true): bool => array_key_exists($key, $data)
        ? filter_var($data[$key], FILTER_VALIDATE_BOOL)
        : $default;
@endphp

<section class="content-block template-content-grid">
    @if (! empty($data['title']))
        <div class="section-heading">
            @include('partials.blocks._heading', ['title' => $data['title'], 'tag' => $data['heading_tag'] ?? 'h2'])
        </div>
    @endif

    @if ($collection instanceof \App\CMS\Collections\Data\CollectionPresentation)
        <div @class([
            'shared-collection',
            'shared-collection--'.$presentationVariant,
            'shared-collection--template',
            'shared-collection--tablet-'.$columnsTablet,
            'shared-collection--ratio-'.str_replace(':', '-', $imageRatio),
            'shared-collection--density-'.$cardDensity,
        ]) dir="{{ $collection->direction === 'ltr' ? 'ltr' : 'rtl' }}">
            @if ($collection->items !== [])
                @include('partials.presentations.collection.grid', [
                    'collection' => $collection,
                    'collectionColumns' => $columnsDesktop,
                    'collectionVariant' => $presentationVariant,
                    'collectionShowImage' => $enabled('show_image'),
                    'collectionShowIcon' => $enabled('show_icon'),
                    'collectionShowExcerpt' => $enabled('show_excerpt'),
                    'collectionShowBadges' => $enabled('show_badges'),
                    'collectionShowMeta' => $enabled('show_meta'),
                    'collectionShowAction' => $enabled('show_action'),
                    'collectionActionLabel' => filled($data['action_label'] ?? null) ? $data['action_label'] : null,
                ])
            @else
                @include('partials.presentations.collection.empty', ['collection' => $collection])
            @endif

            @include('partials.presentations.collection.pagination', ['collection' => $collection])
        </div>
    @else
        <div class="blog-index__grid">
            @forelse ($items as $item)
                @switch($type)
                @case('posts')
                    <article class="blog-card">
                        <a class="blog-card__image" href="{{ route('blog.show', $item->slug) }}" aria-label="{{ $item->title }}">
                            @if ($item->featuredImageUrl('thumb'))
                                <img src="{{ $item->featuredImageUrl('thumb') }}" alt="{{ $item->title }}">
                            @else
                                <span>{{ $item->title }}</span>
                            @endif
                        </a>

                        <div class="blog-card__body">
                            <h2><a href="{{ route('blog.show', $item->slug) }}">{{ $item->title }}</a></h2>

                            @if ($item->published_at)
                                <time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->toFormattedDateString() }}</time>
                            @endif

                            @if ($item->category)
                                <a href="{{ route('blog.category', $item->category->slug) }}">{{ $item->category->title }}</a>
                            @endif
                        </div>
                    </article>
                    @break

                @case('projects')
                    @include('projects.partials.card', ['project' => $item])
                    @break

                @case('products')
                    @include('shop.partials.card', ['product' => $item])
                    @break

                @case('galleries')
                    @include('galleries.partials.card', ['gallery' => $item])
                    @break
                @endswitch
            @empty
                <p class="blog-index__empty">{{ $emptyMessage }}</p>
            @endforelse
        </div>

        @if (is_object($items) && method_exists($items, 'links'))
            <div class="blog-index__pagination">
                {{ $items->links() }}
            </div>
        @endif
    @endif
</section>
