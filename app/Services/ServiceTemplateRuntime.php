<?php

namespace App\Services;

use App\Exceptions\ServiceTemplateUnavailable;
use App\Models\Service;
use App\Models\Template;
use App\Support\SeoData;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class ServiceTemplateRuntime
{
    public function __construct(
        private readonly TemplateService $templates,
        private readonly ServiceQueryService $services,
        private readonly ServiceTemplateContextBuilder $contextBuilder,
    ) {}

    public function render(
        Service $service,
        bool $preview = false,
        ?Template $template = null,
    ): View {
        $template ??= $this->templates->findTemplateFor('service_single', $service);

        if ($template && $template->type !== 'service_single') {
            throw new InvalidArgumentException('Service runtime only accepts service_single templates.');
        }

        if (! $template?->hasBlocks()) {
            throw new ServiceTemplateUnavailable('No renderable service_single template is available.');
        }

        $context = $this->contextBuilder->build($service);
        $templateContext = [
            ...$context['templateContext'],
            ...Arr::except($context, 'templateContext'),
            'isPreview' => $preview,
        ];

        return view('templates.render', [
            'template' => $template,
            'templateContext' => $templateContext,
            'seo' => $preview ? $this->noindex($context['seo']) : $context['seo'],
            'isPreview' => $preview,
        ]);
    }

    public function renderPublishedSlug(string $slug): View
    {
        $service = $this->services->findPublishedBySlug($slug);

        if (! $service) {
            throw (new ModelNotFoundException)->setModel(Service::class, [$slug]);
        }

        return $this->render($service);
    }

    private function noindex(SeoData $seo): SeoData
    {
        return new SeoData(
            title: $seo->title,
            description: $seo->description,
            canonicalUrl: $seo->canonicalUrl,
            robots: 'noindex, nofollow',
            ogTitle: $seo->ogTitle,
            ogDescription: $seo->ogDescription,
            ogImage: $seo->ogImage,
            ogType: $seo->ogType,
            twitterCard: $seo->twitterCard,
            schema: $seo->schema,
        );
    }
}
