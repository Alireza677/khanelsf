@php
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
    $itemsMode = ($data['items_mode'] ?? 'static') === 'dynamic' ? 'dynamic' : 'static';
    $dynamicItems = collect();
    $dynamicGridStyle = '';
    $effectiveColumns = 3;

    if ($itemsMode === 'dynamic') {
        $gap = 16;
        $dynamicSource = ($data['dynamic_source'] ?? 'posts') === 'projects' ? 'projects' : 'posts';
        $rows = max(1, min((int) ($data['dynamic_rows'] ?? 1), 6));
        $requestedColumns = max(1, min((int) ($data['dynamic_columns'] ?? 3), 12));
        $gridWidth = max(240, min((int) ($data['dynamic_grid_width'] ?? 1180), 2400));
        $itemWidth = max(120, min((int) ($data['dynamic_item_width'] ?? 280), 800));
        $widthLimitedColumns = max(1, (int) floor(($gridWidth + $gap) / ($itemWidth + $gap)));
        $effectiveColumns = max(1, min($requestedColumns, $widthLimitedColumns));
        $limit = $rows * $effectiveColumns;
        $buttonLabel = $data['dynamic_button_label'] ?? 'مشاهده بیشتر';
        $buttonOverrides = collect($data['dynamic_button_overrides'] ?? [])
            ->filter(fn ($override) => ! empty($override['record_id']) && ! empty($override['button_label']))
            ->keyBy(fn ($override) => (string) $override['record_id']);

        $records = $dynamicSource === 'projects'
            ? \App\Models\Project::query()->published()->latest('published_at')->take($limit)->get()
            : \App\Models\Post::query()->published()->latest('published_at')->take($limit)->get();

        $dynamicItems = $records->map(function ($record) use ($buttonLabel, $buttonOverrides, $dynamicSource): array {
            $override = $buttonOverrides->get((string) $record->getKey());

            return [
                'title' => $record->title,
                'description' => $record->excerpt,
                'image' => $record->featuredImageUrl('thumb'),
                'button_label' => $override['button_label'] ?? $buttonLabel,
                'button_url' => $dynamicSource === 'projects'
                    ? route('projects.show', $record->slug)
                    : route('blog.show', $record->slug),
            ];
        });

        $dynamicGridStyle = "--feature-grid-width: {$gridWidth}px; --feature-grid-item-width: {$itemWidth}px; --feature-grid-columns: {$effectiveColumns};";
    }

    $items = $itemsMode === 'dynamic'
        ? $dynamicItems
        : collect($data['items'] ?? [])->filter();
@endphp

@include('partials.blocks._image_control_styles')

<section @class([
    'content-block',
    'block-feature-grid',
    "content-block--{$background}" => $background !== 'default',
    "content-block--align-{$alignment}",
])>
    <div class="block-heading">
        @if (! empty($data['eyebrow']))
            <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
        @endif

        @if (! empty($data['section_title']))
            <h2>{{ $data['section_title'] }}</h2>
        @endif

        @if (! empty($data['section_description']))
            <p>{{ $data['section_description'] }}</p>
        @endif
    </div>

    <div @class(['block-grid', 'block-grid--dynamic' => $itemsMode === 'dynamic']) @if ($dynamicGridStyle) style="{{ $dynamicGridStyle }}" @endif>
        @foreach ($items as $item)
            <article class="block-card">
                @if (! empty($item['image']))
                    <img
                        @class(['block-configured-image' => $itemsMode === 'static'])
                        src="{{ $item['image'] }}"
                        alt="{{ $item['title'] ?? '' }}"
                        @if ($itemsMode === 'static') style="{{ \App\Support\BlockImageStyle::imageVariables($item, 'image') }}" @endif
                    >
                @elseif (! empty($item['icon']))
                    <div class="block-card__icon">
                        @include('partials.blocks._icon', ['icon' => $item['icon'], 'size' => $item['icon_size'] ?? null])
                    </div>
                @endif

                @if (! empty($item['title']))
                    <h3>{{ $item['title'] }}</h3>
                @endif

                @if (! empty($item['description']))
                    <p>{{ $item['description'] }}</p>
                @endif

                @if (! empty($item['button_label']) && ! empty($item['button_url']))
                    <a class="button block-card__button" href="{{ $item['button_url'] }}">{{ $item['button_label'] }}</a>
                @endif
            </article>
        @endforeach
    </div>
</section>
