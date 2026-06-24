<?php

namespace App\Filament\Resources\Concerns;

trait UsesPersianResourceLabels
{
    protected static array $persianLabels = [
        'CategoryResource' => ['دسته نوشته', 'دسته‌های نوشته', 'وبلاگ'],
        'ContactMessageResource' => ['پیام تماس', 'پیام‌های تماس', 'صندوق پیام‌ها'],
        'GalleryCategoryResource' => ['دسته گالری', 'دسته‌های گالری', 'گالری‌ها'],
        'GalleryResource' => ['گالری', 'گالری‌ها', 'گالری‌ها'],
        'MediaResource' => ['رسانه', 'رسانه‌ها', 'رسانه'],
        'MenuItemResource' => ['آیتم منو', 'آیتم‌های منو', 'منوها'],
        'MenuResource' => ['منو', 'منوها', 'منوها'],
        'OrderResource' => ['سفارش', 'سفارش‌ها', 'فروشگاه'],
        'PageResource' => ['برگه', 'برگه‌ها', 'محتوا'],
        'PostResource' => ['نوشته', 'نوشته‌ها', 'وبلاگ'],
        'ProductCategoryResource' => ['دسته محصول', 'دسته‌های محصول', 'فروشگاه'],
        'ProductResource' => ['محصول', 'محصولات', 'فروشگاه'],
        'ProjectCategoryResource' => ['دسته پروژه', 'دسته‌های پروژه', 'پروژه‌ها'],
        'ProjectResource' => ['پروژه', 'پروژه‌ها', 'پروژه‌ها'],
        'RedirectResource' => ['ریدایرکت', 'ریدایرکت‌ها', 'نگهداری'],
        'SettingResource' => ['تنظیم', 'تنظیمات', 'تنظیمات سایت'],
        'TemplateResource' => ['قالب', 'قالب‌ها', 'قالب‌ها'],
    ];

    public static function getModelLabel(): string
    {
        return static::persianLabel()[0] ?? parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::persianLabel()[1] ?? parent::getPluralModelLabel();
    }

    public static function getNavigationLabel(): string
    {
        return static::persianLabel()[1] ?? parent::getNavigationLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return static::persianLabel()[2] ?? parent::getNavigationGroup();
    }

    protected static function persianLabel(): array
    {
        return static::$persianLabels[class_basename(static::class)] ?? [];
    }
}
