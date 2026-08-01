<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;

final class ProductIndustrialV1Recipe implements TemplateRecipe
{
    public function key(): string
    {
        return 'product-industrial-v1';
    }

    public function label(): string
    {
        return 'محصول صنعتی';
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
        return 'Blueprint استاندارد برای معرفی، مستندات و فروش محصولات صنعتی.';
    }

    public function compatibility(): array
    {
        $productCapabilities = ['product_context', 'dynamic_data'];

        return [
            'blocks' => [
                'product_header' => ['min_version' => 1, 'capabilities' => $productCapabilities],
                'product_overview' => ['min_version' => 1, 'capabilities' => $productCapabilities],
                'product_specifications' => ['min_version' => 1, 'capabilities' => $productCapabilities],
                'product_gallery' => ['min_version' => 1, 'capabilities' => $productCapabilities],
                'product_documents' => ['min_version' => 1, 'capabilities' => $productCapabilities],
                'product_related' => ['min_version' => 1, 'capabilities' => $productCapabilities],
                'cta' => ['min_version' => 2, 'capabilities' => ['primary_cta']],
            ],
        ];
    }

    public function blocks(): array
    {
        return [
            $this->block('product_header', 1, 'default', [
                'eyebrow' => 'محصول صنعتی',
            ], [
                'show_image' => true,
                'show_category' => true,
                'show_price' => true,
                'show_availability' => true,
                'show_cta' => true,
            ]),
            $this->block('product_overview', 1, 'default', [
                'title' => 'معرفی محصول',
            ], []),
            $this->block('product_specifications', 1, 'default', [
                'title' => 'مشخصات فنی',
            ], [
                'layout' => 'table',
                'show_group' => true,
                'show_unit' => true,
            ]),
            $this->block('product_gallery', 1, 'default', [
                'title' => 'گالری محصول',
            ], [
                'columns' => 3,
                'lightbox' => true,
            ]),
            $this->block('product_documents', 1, 'default', [
                'title' => 'اسناد و فایل‌ها',
            ], [
                'show_type' => true,
            ]),
            $this->block('product_related', 1, 'default', [
                'title' => 'محصولات مرتبط',
            ], [
                'limit' => 3,
            ]),
            $this->block('cta', 2, 'classic', [
                'eyebrow' => null,
                'title' => 'برای انتخاب محصول مناسب نیاز به مشاوره دارید؟',
                'description' => 'برای دریافت راهنمایی فنی و بررسی نیاز پروژه با ما در تماس باشید.',
                'primary_cta' => [
                    'label' => 'دریافت مشاوره',
                    'action' => [
                        'type' => 'url',
                        'url' => '/contact',
                        'form_id' => null,
                        'display' => null,
                    ],
                ],
                'secondary_cta' => [
                    'label' => null,
                    'action' => [
                        'type' => 'url',
                        'url' => null,
                        'form_id' => null,
                        'display' => null,
                    ],
                ],
                'media' => ['url' => null],
            ], [
                'heading_tag' => 'h2',
                'alignment' => 'center',
                'background' => 'dark',
                'content_width' => null,
                'media' => [
                    'desktop' => $this->emptyMediaSettings(),
                    'mobile' => $this->emptyMediaSettings(),
                ],
            ]),
        ];
    }

    private function block(string $type, int $schemaVersion, string $template, array $content, array $settings): array
    {
        return [
            'type' => $type,
            'data' => [
                'block_id' => null,
                'schema_version' => $schemaVersion,
                'template' => $template,
                'content' => $content,
                'settings' => $settings,
            ],
        ];
    }

    private function emptyMediaSettings(): array
    {
        return [
            'width' => ['value' => null, 'unit' => null],
            'height' => ['value' => null, 'unit' => null],
            'fit' => 'normal',
        ];
    }
}
