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
            'brand',
            'attributeAssignments.attribute',
            'attributeAssignments.value',
        ]);
        $media = $this->media->context($product);
        $related = $this->products->relatedProducts($product, $relatedLimit);
        $freshness = app(ProductPriceFreshness::class);
        $availability = [
            ...$this->availability->availability($product),
            'priceFreshness' => $freshness->presentation($product),
            'relatedPriceFreshness' => $related->mapWithKeys(fn (Product $item) => [
                $item->id => $freshness->presentation($item, withInquiry: false),
            ])->all(),
        ];

        $visibleCategories = app(ShopCategoryTree::class)->visible()->keyBy('id');
        $breadcrumbs = [
            ['label' => 'خانه', 'url' => url('/')],
            ['label' => 'فروشگاه', 'url' => route('shop.index')],
        ];
        $categoryPath = [];
        $category = $visibleCategories->get($product->product_category_id);
        while ($category) {
            array_unshift($categoryPath, ['label' => $category->name, 'url' => route('shop.category', $category->slug)]);
            $category = $visibleCategories->get($category->parent_id);
        }
        $breadcrumbs = [...$breadcrumbs, ...$categoryPath, ['label' => $product->title, 'url' => null]];
        $attributes = $product->attributeAssignments
            ->filter(fn ($assignment) => $assignment->attribute?->status === 'active'
                && $assignment->value?->status === 'active'
                && $assignment->value->product_attribute_id === $assignment->attribute->id)
            ->groupBy('product_attribute_id')
            ->map(fn ($assignments) => [
                'id' => $assignments->first()->attribute->id,
                'name' => $assignments->first()->attribute->name,
                'unit' => $assignments->first()->attribute->unit,
                'values' => $assignments->pluck('value.name')->values()->all(),
            ])->values()->all();
        $gallery = collect([$media['featured'], ...$media['gallery']])
            ->filter(fn ($image) => filled($image['url'] ?? null))
            ->unique('url')->values()->all();
        $relatedCards = $related->mapWithKeys(function (Product $item) use ($availability): array {
            return [$item->id => [
                'image' => $item->featuredImageUrl('thumb'),
                'url' => route('shop.show', $item->slug),
                'priceFreshness' => $availability['relatedPriceFreshness'][$item->id],
            ]];
        })->all();

        return [
            'product' => $product,
            'category' => $product->category,
            'effectivePrice' => $this->pricing->effectivePrice($product),
            'currency' => $this->pricing->currency(),
            'availability' => $availability,
            'specifications' => $product->specifications,
            'documents' => $product->documents,
            'media' => $media,
            'relatedProducts' => $related,
            'seo' => $this->seo->forProduct($product),
            'breadcrumbs' => $breadcrumbs,
            'displayCategory' => $visibleCategories->get($product->product_category_id),
            'brand' => $product->brand?->status === 'active' ? ['name' => $product->brand->name] : null,
            'globalAttributes' => $attributes,
            'galleryPresentation' => $gallery,
            'isFavorite' => request()->hasSession() && in_array((int) $product->id, array_map('intval', (array) request()->session()->get('shop.favorite_product_ids', [])), true),
            'relatedCards' => $relatedCards,
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
