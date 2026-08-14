<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;

final class ProjectCaseStudyRecipe implements TemplateRecipe
{
    public function key(): string
    {
        return 'project_case_study';
    }

    public function label(): string
    {
        return 'مطالعه موردی پروژه';
    }

    public function version(): int
    {
        return 1;
    }

    public function targetType(): string
    {
        return 'project_single';
    }

    public function description(): string
    {
        return 'قالب استاندارد و نتیجه‌محور برای نمایش پروژه‌های ساختمانی، صنعتی و B2B.';
    }

    public function compatibility(): array
    {
        $projectCapabilities = ['project_context', 'dynamic_data'];

        return [
            'blocks' => [
                'project_header' => ['min_version' => 1, 'capabilities' => $projectCapabilities],
                'project_overview' => ['min_version' => 1, 'capabilities' => $projectCapabilities],
                'project_metrics' => ['min_version' => 1, 'capabilities' => $projectCapabilities],
                'project_story' => ['min_version' => 1, 'capabilities' => $projectCapabilities],
                'project_services' => ['min_version' => 1, 'capabilities' => $projectCapabilities],
                'project_gallery' => ['min_version' => 1, 'capabilities' => $projectCapabilities],
                'cta' => ['min_version' => 2, 'capabilities' => ['primary_cta']],
                'related_projects' => ['min_version' => 1, 'capabilities' => $projectCapabilities],
            ],
        ];
    }

    public function blocks(): array
    {
        return [
            $this->block('project_header', 1, 'default', [
                'eyebrow' => 'مطالعه موردی',
            ], [
                'variant' => 'default',
                'alignment' => 'start',
                'show_image' => true,
                'show_category' => true,
                'show_client' => false,
                'show_location' => false,
                'show_industry' => false,
                'show_project_type' => false,
                'show_dates' => false,
                'date_format' => 'human',
                'show_cta' => false,
                'cta_type' => 'project',
                'cta_label' => 'مشاهده پروژه',
                'cta_target' => null,
                'show_secondary_cta' => false,
                'secondary_cta_label' => null,
                'secondary_cta_target' => null,
                'heading_tag' => 'h1',
            ]),
            $this->block('project_overview', 1, 'default', [
                'title' => 'نمای کلی پروژه',
            ], [
                'show_client' => true,
                'show_location' => true,
                'show_industry' => true,
                'show_project_type' => true,
                'show_dates' => true,
                'date_format' => 'human',
                'heading_tag' => 'h2',
            ]),
            $this->block('project_metrics', 1, 'default', [
                'title' => 'دستاوردهای کلیدی',
            ], [
                'heading_tag' => 'h2',
            ]),
            $this->block('project_story', 1, 'default', [
                'title' => 'داستان پروژه',
                'headings' => [
                    'challenge' => 'چالش پروژه',
                    'solution' => 'راهکار اجراشده',
                    'results_summary' => 'خلاصه نتایج',
                    'client_quote' => 'نظر کارفرما',
                ],
            ], [
                'show_challenge' => true,
                'show_solution' => true,
                'show_results_summary' => true,
                'show_client_quote' => true,
                'heading_tag' => 'h2',
            ]),
            $this->block('project_services', 1, 'default', [
                'title' => 'خدمات ارائه‌شده',
            ], [
                'heading_tag' => 'h2',
            ]),
            $this->block('project_gallery', 1, 'default', [
                'title' => 'تصاویر پروژه',
            ], [
                'lightbox' => true,
                'heading_tag' => 'h2',
            ]),
            $this->block('cta', 2, 'classic', [
                'eyebrow' => null,
                'title' => 'برای پروژه بعدی آماده‌اید؟',
                'description' => 'برای بررسی نیازها و شروع همکاری با ما در تماس باشید.',
                'primary_cta' => [
                    'label' => 'شروع گفتگو',
                    'action' => [
                        'schema_version' => 1,
                        'type' => 'custom_url',
                        'value' => '/contact',
                        'open_in_new_tab' => false,
                    ],
                ],
                'secondary_cta' => [
                    'label' => null,
                    'action' => null,
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
            $this->block('related_projects', 1, 'default', [
                'title' => 'پروژه‌های مرتبط',
            ], [
                'limit' => 3,
                'heading_tag' => 'h2',
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
