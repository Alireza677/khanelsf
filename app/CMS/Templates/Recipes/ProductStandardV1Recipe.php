<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;

final class ProductStandardV1Recipe implements TemplateRecipe
{
    public function key(): string
    {
        return 'product-standard-v1';
    }

    public function label(): string
    {
        return 'Product Standard — محصول استاندارد';
    }

    public function version(): int
    {
        return 1;
    }

    public function targetType(): string
    {
        return 'product_single';
    }

    public function description(): string
    {
        return 'قالب عمومی محصول ساده با گالری، خرید و اطلاعات محصول؛ بدون ادعا یا واحد فروش پیش‌فرض.';
    }

    public function compatibility(): array
    {
        return ['blocks' => [
            'product_summary' => ['min_version' => 1, 'capabilities' => ['product_context', 'dynamic_data']],
            'feature_grid' => ['min_version' => 1, 'capabilities' => ['static_items']],
            'product_details' => ['min_version' => 1, 'capabilities' => ['product_context', 'dynamic_data']],
            'product_related' => ['min_version' => 1, 'capabilities' => ['product_context', 'dynamic_data']],
            'cta' => ['min_version' => 2, 'capabilities' => ['primary_cta']],
        ]];
    }

    public function blocks(): array
    {
        $registry = app(BlockRegistry::class);
        $blocks = [];
        foreach ([
            'product_summary' => [],
            'feature_grid' => ['content' => ['section_title' => 'مزیت‌های خرید'], 'settings' => ['variant' => 'icon_list']],
            'product_details' => [],
            'product_related' => ['content' => ['title' => 'محصولات مرتبط'], 'settings' => ['limit' => 4, 'variant' => 'compact_visual']],
            'cta' => [],
        ] as $type => $data) {
            $normalizer = $type === 'cta' ? app(CTADataNormalizer::class) : $registry->find($type);
            $blocks[] = ['type' => $type, 'data' => $normalizer->normalize($data)];
        }

        return $blocks;
    }
}
