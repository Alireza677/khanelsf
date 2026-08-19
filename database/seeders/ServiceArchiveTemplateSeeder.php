<?php

namespace Database\Seeders;

use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ServiceArchiveTemplateSeeder extends Seeder
{
    public const TEMPLATE_SLUG = 'service-archive-standard-fa-v1';

    public const TEMPLATE_TYPE = 'service_index';

    public function run(): void
    {
        DB::transaction(function (): void {
            $template = Template::query()
                ->where('slug', self::TEMPLATE_SLUG)
                ->lockForUpdate()
                ->first();

            if ($template) {
                if ($template->type !== self::TEMPLATE_TYPE) {
                    throw new RuntimeException('The service archive template slug is already used by another template type.');
                }

                // Once installed, this record belongs to the editor. Re-seeding
                // must not replace presentation choices made in Template Builder.
                return;
            }

            $template = new Template([
                'title' => 'قالب استاندارد آرشیو خدمات',
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
                throw new RuntimeException('The service archive template is not publishable: '.implode(' ', $errors));
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
                    'eyebrow' => 'خدمات',
                    'title' => 'خدمات حرفه‌ای برای رشد کسب‌وکار شما',
                    'description' => 'با ترکیب تجربه، خلاقیت و فناوری‌های روز، خدماتی ارائه می‌دهیم که حضور دیجیتال شما را قدرتمندتر می‌کند، مشتریان بیشتری جذب می‌کند و مسیر رشد پایدار کسب‌وکارتان را هموار می‌سازد.',
                    'heading_tag' => 'h1',
                    'variant' => 'modern',
                    'alignment' => 'center',
                    'spacing' => 'comfortable',
                    'background_type' => 'gradient',
                    'background_color' => '#f5f8ff',
                    'gradient_from' => '#f5f8ff',
                    'gradient_to' => '#eef4ff',
                    'background_image' => null,
                    'overlay_opacity' => 45,
                ],
            ],
            [
                'type' => 'template_content_grid',
                'data' => [
                    'empty_message' => 'هنوز خدمتی منتشر نشده است.',
                    'columns_desktop' => 3,
                    'columns_tablet' => 2,
                    'image_ratio' => '16:10',
                    'card_density' => 'comfortable',
                    'show_image' => true,
                    'show_icon' => true,
                    'show_excerpt' => true,
                    'show_badges' => true,
                    'show_meta' => true,
                    'show_action' => true,
                    'action_label' => 'مشاهده جزئیات',
                ],
            ],
        ];
    }
}
