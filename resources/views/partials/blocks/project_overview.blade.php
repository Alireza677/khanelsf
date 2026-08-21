@php
    $data = app(\App\CMS\Blocks\Project\ProjectOverviewBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $settings = $data['settings'];
    $dateFormat = static fn ($date) => $settings['date_format'] === 'year'
        ? \App\Support\PersianDate::year($date)
        : \App\Support\PersianDate::dateWithWeekday($date);
    $startedAt = $project?->project_started_at;
    $completedAt = $project?->project_completed_at;
    $legacyDate = ! $startedAt && ! $completedAt ? $project?->project_date : null;
    $items = collect();

    if ($project) {
        collect([
            ['show' => $settings['show_client'], 'label' => 'کارفرما', 'value' => $project->client_name],
            ['show' => $settings['show_location'], 'label' => 'موقعیت پروژه', 'value' => $project->location],
            ['show' => $settings['show_industry'], 'label' => 'حوزه فعالیت', 'value' => $project->industry],
            ['show' => $settings['show_project_type'], 'label' => 'نوع پروژه', 'value' => $project->project_type],
            ['show' => $settings['show_dates'] && (bool) $startedAt, 'label' => 'تاریخ شروع', 'value' => $startedAt ? $dateFormat($startedAt) : null],
            ['show' => $settings['show_dates'] && (bool) $completedAt, 'label' => 'تاریخ پایان', 'value' => $completedAt ? $dateFormat($completedAt) : null],
            ['show' => $settings['show_dates'] && (bool) $legacyDate, 'label' => 'تاریخ پروژه', 'value' => $legacyDate ? $dateFormat($legacyDate) : null],
        ])->filter(fn (array $item): bool => $item['show'] && filled($item['value']))
            ->each(fn (array $item) => $items->push([
                'label' => $item['label'],
                'value' => $item['value'],
            ]));

        collect($project->attributes)
            ->filter(fn ($attribute): bool => is_array($attribute)
                && filled($attribute['label'] ?? null)
                && filled($attribute['value'] ?? null))
            ->each(fn (array $attribute) => $items->push([
                'label' => trim((string) $attribute['label']),
                'value' => trim((string) $attribute['value']),
            ]));
    }
@endphp

@if ($project && $items->isNotEmpty())
    <section class="content-block project-section project-overview">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <dl class="project-meta project-overview__facts">
            @foreach ($items as $item)
                <div><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}</dd></div>
            @endforeach
        </dl>
    </section>
@elseif (! $project && app()->hasDebugModeEnabled())
    <p class="empty-state">Project Overview requires a project single context.</p>
@endif
