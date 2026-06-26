@php
    $source = $data['source'] ?? 'featured';
    $limit = max(1, min((int) ($data['limit'] ?? 3), 12));
    $background = in_array($data['section_background'] ?? 'default', ['muted', 'dark'], true) ? $data['section_background'] : 'default';
    $alignment = ($data['alignment'] ?? 'center') === 'left' ? 'left' : 'center';
    $projectsEnabled = filter_var(app(\App\Services\SettingsService::class)->get('projects_enabled', true), FILTER_VALIDATE_BOOLEAN);

    $projects = $projectsEnabled
        ? \App\Models\Project::query()
            ->with('category')
            ->published()
            ->when($source === 'featured', fn ($query) => $query->featured())
            ->when($source === 'category' && ! empty($data['project_category_id']), fn ($query) => $query->where('project_category_id', $data['project_category_id']))
            ->orderBy('sort_order')
            ->latest('published_at')
            ->take($limit)
            ->get()
        : collect();
@endphp

@if ($projects->isNotEmpty())
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

        <div class="block-grid">
            @foreach ($projects as $project)
                @include('projects.partials.card', ['project' => $project])
            @endforeach
        </div>

        @if (! empty($data['button_label']) && ! empty($data['button_url']))
            <p class="block-more"><a class="button" href="{{ $data['button_url'] }}">{{ $data['button_label'] }}</a></p>
        @endif
    </section>
@endif
