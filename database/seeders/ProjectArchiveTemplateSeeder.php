<?php

namespace Database\Seeders;

use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProjectArchiveTemplateSeeder extends Seeder
{
    public const TEMPLATE_SLUG = 'project-archive-gallery-fa-v1';
    public const TEMPLATE_TYPE = 'projects_index';

    public function run(): void
    {
        DB::transaction(function (): void {
            $template = Template::query()->where('slug', self::TEMPLATE_SLUG)->lockForUpdate()->first();

            if ($template) {
                if ($template->type !== self::TEMPLATE_TYPE) {
                    throw new RuntimeException('The project archive template slug is already used by another template type.');
                }

                return;
            }

            $template = new Template([
                'title' => 'قالب گالری آرشیو پروژه‌ها',
                'slug' => self::TEMPLATE_SLUG,
                'type' => self::TEMPLATE_TYPE,
                'status' => 'published',
                'priority' => 0,
                'is_default' => true,
                'conditions' => ['type' => 'all'],
                'blocks' => $this->blocks(),
            ]);

            $errors = app(TemplatePublicationValidator::class)->validate($template->toArray());

            if ($errors !== []) {
                throw new RuntimeException('The project archive template is not publishable: '.implode(' ', $errors));
            }

            Template::query()->where('type', self::TEMPLATE_TYPE)->where('is_default', true)->update(['is_default' => false]);
            $template->save();
        }, 3);
    }

    /** @return array<int, array{type: string, data: array<string, mixed>}> */
    private function blocks(): array
    {
        return [
            [
                'type' => 'template_archive_header',
                'data' => [
                    'eyebrow' => 'پروژه‌ها',
                    'title' => 'نمونه پروژه‌ها و تجربه‌های اجرایی ما',
                    'description' => 'گزیده‌ای از پروژه‌های انجام‌شده، تجربه‌های عملی و کیفیت اجرای ما را در این مجموعه ببینید.',
                    'heading_tag' => 'h1',
                    'variant' => 'modern',
                    'alignment' => 'center',
                    'spacing' => 'compact',
                    'background_type' => 'gradient',
                    'background_color' => '#f7f9fc',
                    'gradient_from' => '#f8fafc',
                    'gradient_to' => '#eef3f8',
                    'background_image' => null,
                    'overlay_opacity' => 40,
                ],
            ],
            [
                'type' => 'template_content_grid',
                'data' => [
                    'empty_message' => 'هنوز پروژه‌ای منتشر نشده است.',
                    'presentation_variant' => 'masonry_gallery',
                    'columns_desktop' => 3,
                    'columns_tablet' => 2,
                    'image_ratio' => '4:3',
                    'card_density' => 'compact',
                    'show_image' => true,
                    'show_icon' => false,
                    'show_excerpt' => true,
                    'show_badges' => true,
                    'show_meta' => true,
                    'show_action' => true,
                    'action_label' => 'مشاهده پروژه',
                ],
            ],
        ];
    }
}
