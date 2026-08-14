<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersianProjectDetailDemoSeeder extends Seeder
{
    public const PROJECT_SLUG = 'modern-lsf-villa-kerman';

    public function run(): void
    {
        DB::transaction(function (): void {
            $project = Project::query()->firstOrCreate(
                ['slug' => self::PROJECT_SLUG],
                [
                    'title' => 'ساخت ویلای مدرن با سازه LSF در کرمان',
                    'excerpt' => 'طراحی و اجرای یک ویلای مسکونی مدرن در کرمان با استفاده از سیستم سازه سبک فولادی LSF، با تمرکز بر سرعت اجرا، کاهش وزن سازه و کیفیت بالای ساخت.',
                    'content' => '<p>این پروژه شامل طراحی و اجرای یک ویلای مسکونی با سیستم سازه سبک فولادی LSF در شهر کرمان است. هدف اصلی پروژه، دستیابی به سازه‌ای سبک، مقاوم و قابل اجرای سریع بود که در عین حال امکان اجرای معماری مدرن و تأسیسات مورد نیاز ساختمان را فراهم کند.</p>',
                    'location' => 'کرمان',
                    'industry' => 'ساختمان',
                    'project_type' => 'ویلای مسکونی',
                    'challenge' => 'یکی از مهم‌ترین چالش‌های پروژه، هماهنگی طراحی معماری با سیستم سازه LSF و آماده‌سازی دقیق جزئیات اجرایی پیش از شروع عملیات بود. همچنین شرایط اقلیمی کرمان و ضرورت اجرای صحیح عایق‌کاری حرارتی در تصمیم‌های فنی پروژه مورد توجه قرار گرفت.',
                    'solution' => "پس از بررسی نقشه‌های معماری، مدل سازه‌ای پروژه بر اساس سیستم LSF آماده شد. قطعات سازه با ابعاد مشخص تولید و سپس عملیات مونتاژ و نصب در محل پروژه انجام شد. هماهنگی میان سازه، معماری و مسیرهای تأسیساتی باعث شد مراحل اجرا با خطای کمتر و سرعت بیشتری پیش برود.\n\nفرآیند اجرا در چند مرحله شامل بررسی اولیه، طراحی سازه، آماده‌سازی قطعات، مونتاژ، نصب سازه اصلی و آماده‌سازی برای اجرای پوشش‌های نهایی انجام شد.",
                    'results_summary' => 'استفاده از سیستم LSF باعث کاهش وزن سازه، افزایش سرعت اجرای اسکلت و ایجاد بستر مناسب برای ادامه عملیات معماری و تأسیسات شد. پروژه با ساختاری منظم و قابل کنترل برای مراحل بعدی تحویل شد.',
                    'services' => [
                        ['name' => 'طراحی سازه LSF'],
                        ['name' => 'اجرای سازه LSF'],
                    ],
                    'attributes' => [
                        ['label' => 'سیستم سازه', 'value' => 'LSF'],
                        ['label' => 'کاربری', 'value' => 'مسکونی'],
                    ],
                    'status' => 'published',
                    'published_at' => now(),
                    'is_featured' => false,
                    'sort_order' => 0,
                    'seo_title' => 'ساخت ویلای LSF در کرمان | پروژه اجرای سازه سبک فولادی',
                    'seo_description' => 'معرفی پروژه طراحی و اجرای ویلای مسکونی با سازه LSF در کرمان؛ شامل مراحل طراحی، اجرای سازه سبک فولادی و جزئیات پروژه.',
                    'robots_index' => true,
                    'robots_follow' => true,
                ],
            );

            if (! $project->wasRecentlyCreated) {
                return;
            }

            $project->metrics()->createMany([
                ['label' => 'نوع پروژه', 'value' => 'ویلای مسکونی', 'sort_order' => 1],
                ['label' => 'سیستم سازه', 'value' => 'LSF', 'sort_order' => 2],
                ['label' => 'محل اجرا', 'value' => 'کرمان', 'sort_order' => 3],
                ['label' => 'کاربری', 'value' => 'مسکونی', 'sort_order' => 4],
            ]);

            $service = Service::query()
                ->published()
                ->where('name', 'طراحی سازه LSF')
                ->first();

            if ($service) {
                $project->relatedServices()->syncWithoutDetaching([$service->getKey()]);
            }
        });
    }
}
