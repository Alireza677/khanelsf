<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateSettings();
        $this->updateMenuItems();
        $this->updatePages();
    }

    public function down(): void
    {
        //
    }

    private function updateSettings(): void
    {
        $settings = [
            'site_name' => 'نور',
            'header_cta_label' => 'تماس با ما',
            'shop_label' => 'فروشگاه',
            'shop_index_title' => 'فروشگاه',
            'shop_seo_title' => 'فروشگاه',
            'projects_label' => 'پروژه‌ها',
            'projects_index_title' => 'پروژه‌ها',
            'galleries_label' => 'گالری‌ها',
            'galleries_index_title' => 'گالری‌ها',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')
                ->where('key', $key)
                ->where(function ($query): void {
                    $query->whereNull('value')
                        ->orWhereIn('value', [
                            'Starter CMS',
                            'Contact Us',
                            'Shop',
                            'Projects',
                            'Galleries',
                        ]);
                })
                ->update(['value' => $value]);
        }
    }

    private function updateMenuItems(): void
    {
        $items = [
            'Home' => 'خانه',
            'Blog' => 'وبلاگ',
            'Projects' => 'پروژه‌ها',
            'Galleries' => 'گالری‌ها',
            'Shop' => 'فروشگاه',
            'Contact' => 'تماس',
        ];

        foreach ($items as $old => $new) {
            DB::table('menu_items')
                ->where('title', $old)
                ->update(['title' => $new]);
        }
    }

    private function updatePages(): void
    {
        DB::table('pages')->where('slug', 'home')->where('title', 'Home')->update(['title' => 'خانه']);
        DB::table('pages')->where('slug', 'contact')->where('title', 'Contact')->update(['title' => 'تماس با ما']);
    }
};
