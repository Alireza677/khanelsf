@php
    $source = $data['source'] ?? 'featured';
    $type = $data['type'] ?? 'all';
    $limit = max(1, min((int) ($data['limit'] ?? 3), 12));
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
    $galleriesEnabled = filter_var(app(\App\Services\SettingsService::class)->get('galleries_enabled', true), FILTER_VALIDATE_BOOLEAN);

    $galleries = $galleriesEnabled
        ? \App\Models\Gallery::query()
            ->with(['category', 'project'])
            ->published()
            ->withPublicCategory()
            ->when($source === 'featured', fn ($query) => $query->featured())
            ->when($source === 'category' && ! empty($data['gallery_category_id']), fn ($query) => $query->where('gallery_category_id', $data['gallery_category_id']))
            ->when($source === 'project' && ! empty($data['project_id']), fn ($query) => $query->where('project_id', $data['project_id']))
            ->when(in_array($type, ['image', 'video', 'mixed'], true), fn ($query) => $query->where('type', $type))
            ->orderBy('sort_order')
            ->latest('published_at')
            ->take($limit)
            ->get()
        : collect();
@endphp

@if ($galleriesEnabled && ($galleries->isNotEmpty() || ! empty($data['section_title'])))
    <section @class([
        'content-block',
        "content-block--{$background}" => $background !== 'default',
        "content-block--align-{$alignment}",
    ])>
        <div class="block-heading">
            @if (! empty($data['eyebrow']))
                <p class="block-eyebrow">{{ $data['eyebrow'] }}</p>
            @endif

            @if (! empty($data['section_title']))
                @include('partials.blocks._heading', ['title' => $data['section_title'], 'tag' => $data['heading_tag'] ?? 'h2'])
            @endif

            @if (! empty($data['section_description']))
                <p>{{ $data['section_description'] }}</p>
            @endif
        </div>

        @if ($galleries->isNotEmpty())
            <div class="block-grid">
                @foreach ($galleries as $gallery)
                    @include('galleries.partials.card', ['gallery' => $gallery])
                @endforeach
            </div>
        @else
            <p class="empty-state">No galleries match this block yet.</p>
        @endif

        @if (! empty($data['button_label']) && ! empty($data['button_url']))
            <p class="block-more"><a class="button" href="{{ $data['button_url'] }}">{{ $data['button_label'] }}</a></p>
        @endif
    </section>
@endif
