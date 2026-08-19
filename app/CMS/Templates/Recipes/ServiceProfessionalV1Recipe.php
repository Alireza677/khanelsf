<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;

final class ServiceProfessionalV1Recipe implements TemplateRecipe
{
    public function key(): string
    {
        return 'service-professional-v1';
    }

    public function label(): string
    {
        return 'صفحه حرفه‌ای خدمت';
    }

    public function version(): int
    {
        return 3;
    }

    public function targetType(): string
    {
        return 'service_single';
    }

    public function description(): string
    {
        return 'قالب استاندارد برای معرفی حرفه‌ای خدمت، فرآیند اجرا، خروجی‌ها، نمونه‌پروژه‌ها و دعوت به اقدام.';
    }

    public function compatibility(): array
    {
        $serviceCapabilities = ['service_context', 'dynamic_data'];

        return [
            'blocks' => [
                'service_header' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'service_overview' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'service_benefits' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'service_process' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'service_deliverables' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'service_projects' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'service_gallery' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'related_services' => ['min_version' => 1, 'capabilities' => $serviceCapabilities],
                'cta' => ['min_version' => 2, 'capabilities' => ['primary_cta']],
            ],
        ];
    }

    public function blocks(): array
    {
        return [
            $this->block('service_header', 1, 'default', [], [
                'show_excerpt' => true,
                'show_image' => true,
                'alignment' => 'start',
                'variant' => 'modern-split',
                'image_position' => 'end',
                'primary_action' => [
                    'label' => 'شروع همکاری',
                    'action' => $this->placeholderAction(),
                ],
                'secondary_action' => [
                    'label' => 'مشاوره و گفتگو',
                    'action' => $this->placeholderAction(),
                ],
            ]),
            $this->block('service_overview', 1, 'default', [
                'title' => 'معرفی خدمت',
            ], [
                'width' => 'default',
                'variant' => 'professional',
            ]),
            $this->block('service_benefits', 1, 'default', [
                'title' => 'مزایای این خدمت',
            ], [
                'columns' => 3,
                'show_icons' => true,
                'variant' => 'icon-cards',
            ]),
            $this->block('service_process', 1, 'default', [
                'title' => 'فرآیند اجرای خدمت',
            ], [
                'layout' => 'horizontal',
                'show_steps' => true,
                'variant' => 'connected-steps',
            ]),
            $this->block('service_deliverables', 1, 'default', [
                'title' => 'خروجی‌ها و اقلام تحویلی',
            ], [
                'style' => 'cards',
                'columns' => 3,
                'variant' => 'compact-grid',
            ]),
            $this->block('service_projects', 1, 'default', [
                'title' => 'پروژه‌های مرتبط',
            ], [
                'columns' => 3,
                'variant' => 'visual-cards',
            ]),
            $this->block('service_gallery', 1, 'default', [
                'title' => 'گالری تصاویر',
            ], [
                'columns' => 3,
                'lightbox' => true,
                'variant' => 'horizontal-gallery',
            ]),
            $this->block('related_services', 1, 'default', [
                'title' => 'خدمات مرتبط',
            ], [
                'columns' => 3,
            ]),
            $this->block('cta', 2, 'classic', [
                'eyebrow' => null,
                'title' => null,
                'description' => null,
                'primary_cta' => [
                    'label' => null,
                    'action' => [
                        'type' => 'url',
                        'url' => null,
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

    private function placeholderAction(): array
    {
        return [
            'schema_version' => 1,
            'type' => 'custom_url',
            'value' => '#',
            'open_in_new_tab' => false,
        ];
    }
}
