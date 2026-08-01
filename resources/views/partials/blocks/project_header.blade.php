@php
    $data = app(\App\CMS\Blocks\Project\ProjectHeaderBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $settings = $data['settings'];
    $category = $project?->relationLoaded('category') ? $project->getRelation('category') : null;
    $featuredMedia = $project?->relationLoaded('media')
        ? $project->getRelation('media')->first(fn ($media) => $media->collection_name === 'featured_image')
        : null;
    $imageUrl = $featuredMedia?->getUrl();
    $formatDate = static fn ($date): ?string => ! $date
        ? null
        : ($settings['date_format'] === 'year' ? $date->format('Y') : $date->toFormattedDateString());
    $startedAt = $project?->project_started_at;
    $completedAt = $project?->project_completed_at;
    $legacyDate = ! $startedAt && ! $completedAt ? $project?->project_date : null;
    $dates = collect([$formatDate($startedAt), $formatDate($completedAt), $formatDate($legacyDate)])
        ->filter()
        ->unique()
        ->implode(' – ');
    $metadata = $project ? collect([
        'category' => $category?->name,
        'client' => $project->client_name,
        'location' => $project->location,
        'industry' => $project->industry,
        'project_type' => $project->project_type,
        'dates' => $dates,
    ])->filter(fn ($value, string $key): bool => $settings["show_{$key}"] && filled($value)) : collect();
    $metadataLabels = [
        'category' => 'دسته‌بندی',
        'client' => 'کارفرما',
        'location' => 'موقعیت پروژه',
        'industry' => 'حوزه فعالیت',
        'project_type' => 'نوع پروژه',
        'dates' => 'تاریخ پروژه',
    ];
    $primaryUrl = $settings['cta_type'] === 'project'
        ? $project?->external_url
        : $settings['cta_target'];
    $showPrimaryCta = $settings['show_cta'] && filled($settings['cta_label']) && filled($primaryUrl);
    $showSecondaryCta = $settings['show_secondary_cta']
        && filled($settings['secondary_cta_label'])
        && filled($settings['secondary_cta_target']);
@endphp

@if ($project)
    <section
        class="content-block project-header project-header--{{ $settings['variant'] }} project-header--align-{{ $settings['alignment'] }}"
        dir="rtl"
    >
        <div class="project-header__content">
            @if ($data['content']['eyebrow'])
                <p class="block-eyebrow">{{ $data['content']['eyebrow'] }}</p>
            @endif

            @include('partials.blocks._heading', ['title' => $project->title, 'tag' => data_get($data, 'settings.heading_tag', 'h1')])

            @if ($project->excerpt)
                <p class="project-header__intro">{{ $project->excerpt }}</p>
            @endif

            @if ($metadata->isNotEmpty())
                <dl class="project-meta project-header__meta">
                    @foreach ($metadata as $key => $value)
                        <div>
                            <dt>{{ $metadataLabels[$key] }}</dt>
                            <dd>
                                @if ($key === 'category')
                                    <a href="{{ route('projects.category', $category->slug) }}">{{ $value }}</a>
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if ($showPrimaryCta || $showSecondaryCta)
                <div class="block-actions project-header__actions">
                    @if ($showPrimaryCta)
                        <a
                            class="button"
                            href="{{ $primaryUrl }}"
                            @if ($settings['cta_type'] === 'project') target="_blank" rel="noopener noreferrer" @endif
                        >{{ $settings['cta_label'] }}</a>
                    @endif

                    @if ($showSecondaryCta)
                        <a class="button button-secondary" href="{{ $settings['secondary_cta_target'] }}">
                            {{ $settings['secondary_cta_label'] }}
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if ($settings['show_image'] && $imageUrl)
            <div class="project-header__media">
                <img class="project-detail__image" src="{{ $imageUrl }}" alt="{{ $project->title }}">
            </div>
        @endif
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Project Header requires a project single context.</p>
@endif
