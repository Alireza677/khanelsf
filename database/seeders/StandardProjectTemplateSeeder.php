<?php

namespace Database\Seeders;

use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\Models\Template;
use Illuminate\Database\Seeder;
use RuntimeException;

class StandardProjectTemplateSeeder extends Seeder
{
    public const TEMPLATE_SLUG = 'project-standard-fa-v1';

    public function run(): void
    {
        $existing = Template::query()->where('slug', self::TEMPLATE_SLUG)->first();

        if ($existing) {
            if ($existing->type !== 'project_single') {
                throw new RuntimeException('The standard project template slug is already used by another template type.');
            }

            return;
        }

        $template = app(TemplateRecipeInstantiator::class)->makeDraft('project_case_study', [
            'title' => 'قالب استاندارد نمایش پروژه',
            'slug' => self::TEMPLATE_SLUG,
            'priority' => 0,
            'is_default' => true,
            'conditions' => ['type' => 'all'],
        ]);

        $template->blocks = collect($template->blocks)->map(function (array $block): array {
            $type = $block['type'] ?? null;

            if ($type === 'project_overview') {
                data_set($block, 'data.content.title', 'معرفی پروژه');
            } elseif ($type === 'project_story') {
                data_set($block, 'data.content.title', 'چالش و راهکار');
                data_set($block, 'data.content.headings.solution', 'راهکار اجرا');
                data_set($block, 'data.content.headings.results_summary', 'نتایج پروژه');
            } elseif ($type === 'project_metrics') {
                data_set($block, 'data.content.title', 'دستاوردها و مزایا');
            } elseif ($type === 'project_services') {
                data_set($block, 'data.content.title', 'خدمات پروژه');
            } elseif ($type === 'project_gallery') {
                data_set($block, 'data.content.title', 'تصاویر پروژه');
            } elseif ($type === 'cta') {
                data_set($block, 'data.content.title', 'برای اجرای پروژه‌ای جدید برنامه دارید؟');
                data_set($block, 'data.content.description', 'برای بررسی نیازهای پروژه و دریافت مشاوره اولیه با ما در ارتباط باشید.');
                data_set($block, 'data.content.primary_cta.label', 'درخواست مشاوره');
            }

            return $block;
        })->all();
        $template->status = 'published';
        Template::query()
            ->where('type', 'project_single')
            ->where('is_default', true)
            ->update(['is_default' => false]);
        $template->save();
    }
}
