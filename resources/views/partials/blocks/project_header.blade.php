@php
    $data = app(\App\CMS\Blocks\Project\ProjectHeaderBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $project = ($context['model'] ?? null) instanceof \App\Models\Project ? $context['model'] : null;
    $settings = $data['settings'];
    $category = $project?->relationLoaded('category') ? $project->getRelation('category') : null;
    $media = $project?->relationLoaded('media') ? $project->getRelation('media') : collect();
    $cover = $media->first(fn ($item) => $item->collection_name === 'featured_image')
        ?? $media->first(fn ($item) => $item->collection_name === 'gallery');
    $formatDate = static fn ($date): ?string => ! $date ? null
        : ($settings['date_format'] === 'year' ? \App\Support\PersianDate::year($date) : \App\Support\PersianDate::dateWithWeekday($date));
    $startedAt = $project?->project_started_at;
    $completedAt = $project?->project_completed_at;
    $legacyDate = ! $startedAt && ! $completedAt ? $project?->project_date : null;
    $dates = collect([$formatDate($startedAt), $formatDate($completedAt), $formatDate($legacyDate)])->filter()->unique()->implode(' – ');
    $metaCandidates = $project ? [
        ['key' => 'category', 'label' => 'دسته‌بندی', 'value' => $category?->name],
        ['key' => 'location', 'label' => 'موقعیت', 'value' => $project->location],
        ['key' => 'project_type', 'label' => 'نوع پروژه', 'value' => $project->project_type],
        ['key' => 'industry', 'label' => 'حوزه فعالیت', 'value' => $project->industry],
        ['key' => 'client', 'label' => 'کارفرما', 'value' => $project->client_name],
        ['key' => 'dates', 'label' => 'تاریخ اجرا', 'value' => $dates],
    ] : [];
    $metaItems = collect($metaCandidates)
        ->filter(fn (array $item): bool => ($settings['show_'.$item['key']] ?? false) && filled($item['value']))
        ->map(fn (array $item): array => ['label' => $item['label'], 'value' => $item['value']])
        ->values()->all();
    $resolutionContext = new \App\CMS\Actions\Data\ResolutionContext(
        (! empty($isPreview) || ! empty($context['preview']))
            ? \App\CMS\Actions\Enums\ResolutionMode::Preview
            : \App\CMS\Actions\Enums\ResolutionMode::Production,
    );
    $resolver = app(\App\CMS\Actions\Resolution\RuntimeActionResolver::class);
    $presenter = app(\App\CMS\Actions\Presentation\ActionPresentation::class);
    $presentAction = static function (?string $label, ?array $destination) use ($resolver, $presenter, $resolutionContext, $data, $context): ?array {
        if (blank($label) || ! is_array($destination)) return null;
        $resolved = $resolver->resolve(\App\CMS\Actions\Data\ActionDestination::fromArray($destination), $resolutionContext);
        $presentation = $presenter->present($resolved, [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? request()->getRequestUri(),
            'block_id' => $data['block_id'],
        ]);
        return $presentation ? ['label' => $label, 'presentation' => $presentation] : null;
    };
    $primary = $presentAction($settings['primary_action']['label'], $settings['primary_action']['action']);
    $secondary = $presentAction($settings['secondary_action']['label'], $settings['secondary_action']['action']);
    if (! $primary && $settings['show_cta']) {
        $legacyUrl = $settings['cta_type'] === 'project' ? $project?->external_url : $settings['cta_target'];
        $primary = $presentAction($settings['cta_label'], filled($legacyUrl) ? [
            'schema_version' => 1, 'type' => 'custom_url', 'value' => $legacyUrl,
            'open_in_new_tab' => $settings['cta_type'] === 'project',
        ] : null);
    }
    if (! $secondary && $settings['show_secondary_cta']) {
        $secondary = $presentAction($settings['secondary_cta_label'], filled($settings['secondary_cta_target']) ? [
            'schema_version' => 1, 'type' => 'custom_url', 'value' => $settings['secondary_cta_target'], 'open_in_new_tab' => false,
        ] : null);
    }
    $hero = $project ? [
        'class' => 'project-case-study__hero',
        'eyebrow' => $data['content']['eyebrow'] ?: $category?->name,
        'title' => $project->title,
        'description' => $project->excerpt,
        'image' => $settings['show_image'] && $cover ? ['url' => $cover->getUrl(), 'alt' => $project->title, 'loading' => 'eager'] : null,
        'primary_action' => $primary,
        'secondary_action' => $secondary,
        'meta_items' => $metaItems,
        'variant' => $settings['variant'],
        'alignment' => $settings['alignment'],
        'image_position' => 'end',
        'background' => $settings['variant'] === 'cover' ? 'dark' : 'default',
        'heading_tag' => $settings['heading_tag'],
    ] : null;
@endphp

@if ($hero)
    @include('partials.presentations.hero', ['hero' => $hero])
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Project Header requires a project single context.</p>
@endif
