<?php

namespace App\Services;

use App\CMS\Blocks\Product\ProductRelatedBlock;
use App\Models\Product;
use App\Models\Template;

final class ProductTemplateContextBuilder
{
    public function __construct(
        private readonly ProductRelatedBlock $relatedProductsBlock,
        private readonly ProductQueryService $products,
        private readonly ProductPricingService $pricing,
        private readonly ProductAvailabilityService $availability,
        private readonly ProductMediaService $media,
        private readonly SeoService $seo,
    ) {}

    public function build(Product $product, int $relatedLimit = 3): array
    {
        $product->loadMissing([
            'category',
            'specifications',
            'documents',
        ]);
        $media = $this->media->context($product);

        return [
            'product' => $product,
            'category' => $product->category,
            'effectivePrice' => $this->pricing->effectivePrice($product),
            'currency' => $this->pricing->currency(),
            'availability' => $this->availability->availability($product),
            'specifications' => $product->specifications,
            'documents' => $product->documents,
            'media' => $media,
            'relatedProducts' => $this->products->relatedProducts($product, $relatedLimit),
            'seo' => $this->seo->forProduct($product),
        ];
    }

    public function relatedLimit(?Template $template): int
    {
        if (! $template?->hasBlocks()) {
            return 3;
        }

        $block = collect($template->blocks)
            ->first(fn (mixed $block): bool => is_array($block)
                && ($block['type'] ?? null) === $this->relatedProductsBlock->key());

        if (! is_array($block)) {
            return 0;
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : $block;

        return $this->relatedProductsBlock->normalize($data)['settings']['limit'];
    }
}
