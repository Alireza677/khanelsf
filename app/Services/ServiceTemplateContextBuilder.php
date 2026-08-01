<?php

namespace App\Services;

use App\Models\Service;

final class ServiceTemplateContextBuilder
{
    public function __construct(
        private readonly ServiceQueryService $services,
        private readonly ServiceMediaService $media,
        private readonly SeoService $seo,
    ) {}

    public function build(Service $service): array
    {
        $service = $this->services->prepareForContext($service);
        $media = $this->media->context($service);
        $projects = $this->services->relatedProjects($service);
        $relatedServices = collect();

        return [
            'entity' => $service,
            'service' => $service,
            'content' => [
                'name' => $service->name,
                'slug' => $service->slug,
                'excerpt' => $service->excerpt,
                'overview' => $service->overview,
                'benefits' => array_values($service->benefits ?? []),
                'process' => array_values($service->process ?? []),
                'deliverables' => array_values($service->deliverables ?? []),
                'icon' => $service->icon,
            ],
            'media' => $media,
            'projects' => $projects,
            'relatedServices' => $relatedServices,
            'seo' => $this->seo->forService($service, $media),
            'templateContext' => [
                'kind' => 'single',
                'type' => 'service',
                'target' => 'service_single',
                'model' => $service,
                'related' => $relatedServices,
                'projects' => $projects,
            ],
        ];
    }
}
