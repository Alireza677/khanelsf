<?php

namespace App\Services;

final class ProjectDiscoveryTemplateContextBuilder
{
    /** @param array{projects: mixed, vocabularies: mixed, active_filters: array} $discovery */
    public function build(array $discovery, string $heading, string $description): array
    {
        return [
            'kind' => 'archive',
            'type' => 'project_discovery',
            'projects' => $discovery['projects'],
            'items' => $discovery['projects'],
            'vocabularies' => $discovery['vocabularies'],
            'active_filters' => $discovery['active_filters'],
            'heading' => $heading,
            'description' => $description,
            'emptyMessage' => 'پروژه‌ای مطابق فیلترهای انتخاب‌شده پیدا نشد.',
            'archive_url' => route('galleries.index'),
            'detail_route' => 'projects.show',
        ];
    }
}
