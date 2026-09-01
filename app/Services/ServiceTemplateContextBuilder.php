<?php

namespace App\Services;

use App\CMS\Collections\Service\ServiceCollectionAdapter;
use App\Models\Service;
use Illuminate\Support\Collection;

final class ServiceTemplateContextBuilder
{
    public function __construct(
        private readonly ServiceQueryService $services,
        private readonly ServiceMediaService $media,
        private readonly SeoService $seo,
        private readonly ServiceCollectionAdapter $collections,
    ) {}

    public function build(Service $service): array
    {
        $service = $this->services->prepareForContext($service);
        $media = $this->media->context($service);
        $projects = $this->services->relatedProjects($service);
        $children = $this->services->publicDirectChildren($service);
        $relatedServices = $this->collections->adaptServices($children, 'خدمات زیرمجموعه');
        $breadcrumbs = $this->breadcrumbs($service, $this->services->publicAncestorChain($service));

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
            'breadcrumbs' => $breadcrumbs,
            'seo' => $this->seo->forService($service, $media, $breadcrumbs),
            'templateContext' => [
                'kind' => 'single',
                'type' => 'service',
                'target' => 'service_single',
                'model' => $service,
                'related' => $relatedServices,
                'projects' => $projects,
                'breadcrumbs' => $breadcrumbs,
            ],
        ];
    }

    private function breadcrumbs(Service $service, Collection $ancestors): array
    {
        return [
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => 'خدمات', 'url' => route('services.index')],
            ...$ancestors->map(fn (Service $ancestor): array => [
                'name' => $ancestor->name,
                'url' => route('services.show', $ancestor->slug),
            ])->all(),
            ['name' => $service->name, 'url' => route('services.show', $service->slug)],
        ];
    }
}
