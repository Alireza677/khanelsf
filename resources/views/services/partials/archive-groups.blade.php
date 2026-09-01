@php
    $columnsDesktop = $columnsDesktop ?? 3;
    $columnsTablet = $columnsTablet ?? 2;
    $imageRatio = $imageRatio ?? '16:10';
    $cardDensity = $cardDensity ?? 'comfortable';
    $presentationVariant = $presentationVariant ?? 'clean_grid';
    $showImage = $showImage ?? true;
    $showIcon = $showIcon ?? true;
    $showExcerpt = $showExcerpt ?? true;
    $showBadges = $showBadges ?? true;
    $showMeta = $showMeta ?? true;
    $showAction = $showAction ?? true;
@endphp

@if ($serviceArchive->groups === [])
    <section class="shared-collection" dir="rtl">
        @include('partials.presentations.collection.empty', ['collection' => $serviceArchive->emptyCollection])
    </section>
@else
    <div class="service-archive-groups">
        @foreach ($serviceArchive->groups as $group)
            @php($groupCollection = $group->collection)
            <section @class([
                'shared-collection',
                'service-archive-group',
                'service-archive-group--root-fallback' => $group->usesRootAsFallback,
                'shared-collection--'.$presentationVariant,
                'shared-collection--template' => $templateMode ?? false,
                'shared-collection--tablet-'.$columnsTablet,
                'shared-collection--ratio-'.str_replace(':', '-', $imageRatio),
                'shared-collection--density-'.$cardDensity,
            ]) dir="rtl" aria-labelledby="service-archive-group-{{ $group->rootId }}">
                <header class="service-archive-group__header">
                    <h2 id="service-archive-group-{{ $group->rootId }}">{{ $groupCollection->title }}</h2>
                    @if ($showExcerpt && filled($groupCollection->description))
                        <p>{{ $groupCollection->description }}</p>
                    @endif
                </header>

                @include('partials.presentations.collection.grid', [
                    'collection' => $groupCollection,
                    'collectionColumns' => $columnsDesktop,
                    'collectionVariant' => $presentationVariant,
                    'collectionShowImage' => $showImage,
                    'collectionShowIcon' => $showIcon,
                    'collectionShowExcerpt' => $showExcerpt,
                    'collectionShowBadges' => $showBadges,
                    'collectionShowMeta' => $showMeta,
                    'collectionShowAction' => $showAction,
                    'collectionActionLabel' => $actionLabel ?? null,
                ])

                @include('partials.presentations.collection.pagination', ['collection' => $groupCollection])
            </section>
        @endforeach
    </div>
@endif
