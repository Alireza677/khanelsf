<?php

namespace Database\Seeders;

use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BlogArchiveTemplateSeeder extends Seeder
{
    public const TEMPLATE_SLUG = 'blog-archive-standard-fa-v1';

    public const TEMPLATE_TYPE = 'blog_index';

    public function run(): void
    {
        DB::transaction(function (): void {
            $template = Template::query()
                ->where('slug', self::TEMPLATE_SLUG)
                ->lockForUpdate()
                ->first();

            if ($template) {
                if ($template->type !== self::TEMPLATE_TYPE) {
                    throw new RuntimeException('The blog archive template slug is already used by another template type.');
                }

                return;
            }

            $template = new Template([
                'title' => 'قالب استاندارد آرشیو وبلاگ',
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
                throw new RuntimeException('The blog archive template is not publishable: '.implode(' ', $errors));
            }

            Template::query()
                ->where('type', self::TEMPLATE_TYPE)
                ->where('is_default', true)
                ->update(['is_default' => false]);

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
                    'eyebrow' => 'وبلاگ',
                    'title' => 'دانش، تجربه و ایده‌هایی برای رشد بهتر',
                    'description' => 'مقالات، راهنماها و تجربه‌های کاربردی درباره طراحی وب، توسعه، سئو و رشد کسب‌وکار دیجیتال.',
                    'heading_tag' => 'h1',
                    'variant' => 'modern',
                    'alignment' => 'center',
                    'spacing' => 'comfortable',
                    'background_type' => 'gradient',
                    'background_color' => '#f8f7ff',
                    'gradient_from' => '#f8f7ff',
                    'gradient_to' => '#eef4ff',
                    'background_image' => null,
                    'overlay_opacity' => 45,
                ],
            ],
            [
                'type' => 'template_content_grid',
                'data' => [
                    'empty_message' => 'هنوز نوشته‌ای منتشر نشده است.',
                    'columns_desktop' => 3,
                    'columns_tablet' => 2,
                    'image_ratio' => '16:10',
                    'card_density' => 'comfortable',
                    'show_image' => true,
                    'show_icon' => false,
                    'show_excerpt' => true,
                    'show_badges' => true,
                    'show_meta' => true,
                    'show_action' => true,
                    'action_label' => 'مطالعه مقاله',
                ],
            ],
        ];
    }
}
