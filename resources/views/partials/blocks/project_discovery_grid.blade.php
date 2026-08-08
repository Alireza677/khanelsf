@php
    $data = app(\App\CMS\Blocks\ProjectDiscovery\ProjectDiscoveryGridBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $settings = $data['settings'];
    $projects = $context['projects'] ?? $context['items'] ?? collect();
    $vocabularies = $context['vocabularies'] ?? collect();
    $activeFilters = $context['active_filters'] ?? [];
@endphp

@if (($context['type'] ?? null) === 'project_discovery')
    <section class="gallery-discovery">
        @if ($settings['show_filters'] && $vocabularies->isNotEmpty())
            <details class="gallery-discovery__filters">
                <summary>فیلتر پروژه‌ها</summary>
                <form class="gallery-discovery__filter-panel" method="GET" action="{{ $context['archive_url'] }}">
                    @foreach ($vocabularies as $vocabulary)
                        <fieldset>
                            <legend>{{ $vocabulary->name }}</legend>
                            <div class="gallery-discovery__filter-options">
                                @foreach ($vocabulary->terms as $term)
                                    <label>
                                        <input type="checkbox" name="filters[{{ $vocabulary->slug }}][]" value="{{ $term->slug }}" @checked(in_array($term->slug, $activeFilters[$vocabulary->slug] ?? [], true))>
                                        <span>{{ $term->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <div class="gallery-discovery__filter-actions">
                        <button class="button" type="submit">اعمال فیلترها</button>
                        @if ($activeFilters !== [])
                            <a href="{{ $context['archive_url'] }}">پاک‌کردن فیلترها</a>
                        @endif
                    </div>
                </form>
            </details>
        @endif

        <div class="gallery-discovery__grid gallery-discovery__grid--columns-{{ $settings['columns'] }}">
            @forelse ($projects as $project)
                @include('galleries.partials.project-discovery-card', [
                    'project' => $project,
                    'imageRatio' => $settings['image_ratio'],
                    'showCategory' => $settings['show_category'],
                    'showDiscoveryTerms' => $settings['show_discovery_terms'],
                ])
            @empty
                <p class="blog-index__empty">{{ $context['emptyMessage'] ?? 'پروژه‌ای پیدا نشد.' }}</p>
            @endforelse
        </div>

        @if (is_object($projects) && method_exists($projects, 'links'))
            <div class="blog-index__pagination">{{ $projects->links() }}</div>
        @endif
    </section>
@else
    <p class="empty-state">Project Discovery Grid requires a Project Discovery archive context.</p>
@endif
