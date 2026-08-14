<?php

namespace App\Filament\Resources\Concerns;

trait UsesPersianResourceLabels
{
    protected static array $persianLabels = [
        'CategoryResource' => ['دسته نوشته', 'دسته‌های نوشته', 'محتوای وب‌سایت'],
        'ContactMessageResource' => ['پیام تماس', 'پیام‌های تماس', 'فروش و ارتباط با مشتری'],
        'CustomerResource' => ['مشتری', 'مشتریان پرتال', 'پرتال مشتریان'],
        'ClientProjectResource' => ['پروژه مشتری', 'پروژه‌های مشتریان', 'پرتال مشتریان'],
        'ClientProjectActivityResource' => ['فعالیت', 'فعالیت‌ها', 'پرتال مشتریان'],
        'FormResource' => ['فرم', 'فرم‌ها', 'فروش و ارتباط با مشتری'],
        'FormSubmissionResource' => ['ورودی فرم', 'ورودی‌ها', 'فروش و ارتباط با مشتری'],
        'LeadResource' => ['سرنخ', 'سرنخ‌ها', 'فروش و ارتباط با مشتری'],
        'SiteUserResource' => ['کاربر سایت', 'کاربران سایت', 'فروش و ارتباط با مشتری'],
        'GalleryCategoryResource' => ['دسته گالری', 'دسته‌های گالری', 'نمونه‌کار و گالری'],
        'GalleryResource' => ['گالری', 'گالری پروژه‌ها', 'نمونه‌کار و گالری'],
        'MediaResource' => ['رسانه', 'رسانه‌ها', 'ساختار و طراحی وب‌سایت'],
        'MenuItemResource' => ['آیتم منو', 'آیتم‌های منو', 'ساختار و طراحی وب‌سایت'],
        'MenuResource' => ['منو', 'منوها', 'ساختار و طراحی وب‌سایت'],
        'OrderResource' => ['سفارش', 'سفارش‌ها', 'خدمات و فروش'],
        'PageResource' => ['برگه', 'برگه‌ها', 'محتوای وب‌سایت'],
        'PostResource' => ['نوشته', 'نوشته‌ها', 'محتوای وب‌سایت'],
        'ProductCategoryResource' => ['دسته محصول', 'دسته‌های محصول', 'خدمات و فروش'],
        'ProductResource' => ['محصول', 'محصولات', 'خدمات و فروش'],
        'ProjectCategoryResource' => ['دسته پروژه', 'دسته‌های پروژه', 'نمونه‌کار و گالری'],
        'ProjectResource' => ['پروژه', 'پروژه‌های عمومی', 'نمونه‌کار و گالری'],
        'ProjectDiscoveryVocabularyResource' => ['فیلتر گالری', 'فیلترهای گالری', 'نمونه‌کار و گالری'],
        'RedirectResource' => ['ریدایرکت', 'ریدایرکت‌ها', 'نگهداری سیستم'],
        'ServiceResource' => ['خدمت', 'خدمات', 'خدمات و فروش'],
        'SettingResource' => ['تنظیم', 'تنظیمات', 'ساختار و طراحی وب‌سایت'],
        'TemplateResource' => ['قالب', 'قالب‌ها', 'ساختار و طراحی وب‌سایت'],
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
