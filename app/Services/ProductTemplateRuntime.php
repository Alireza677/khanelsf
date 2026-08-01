<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Template;
use App\Support\SeoData;
use Illuminate\Contracts\View\View;

final class ProductTemplateRuntime
{
    public function __construct(
        private readonly TemplateService $templates,
        private readonly ProductTemplateContextBuilder $contextBuilder,
    ) {}

    public function render(
        Product $product,
        bool $preview = false,
        ?Template $template = null,
    ): View {
        $template ??= $this->templates->findTemplateFor('product_single', $product);
        $context = $this->contextBuilder->build(
            $product,
            $this->contextBuilder->relatedLimit($template),
        );
        $templateContext = [
            'kind' => 'single',
            'type' => 'product',
            'model' => $product,
            'related' => $context['relatedProducts'],
            ...$context,
            'isPreview' => $preview,
        ];

        return $this->templates->viewOrFallback($template, 'shop.show', [
            ...$context,
            'seo' => $preview ? $this->noindex($context['seo']) : $context['seo'],
            'isPreview' => $preview,
            'templateContext' => $templateContext,
        ]);
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
