<?php

namespace App\Providers\Filament;

use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Services\SettingsService;
use Filament\Forms\Components\Component as FormComponent;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FormComponent::configureUsing(fn (FormComponent $component) => $this->applyPersianLabel($component));
        Column::configureUsing(fn (Column $column) => $this->applyPersianLabel($column));
        BaseFilter::configureUsing(fn (BaseFilter $filter) => $this->applyPersianLabel($filter));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (): string => app(SettingsService::class)->siteName())
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.theme')->render(),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.view-website-button')->render(),
            )
            ->renderHook(
                TablesRenderHook::TOOLBAR_TOGGLE_COLUMN_TRIGGER_AFTER,
                fn (): string => view('filament.media.view-switcher')->render(),
                scopes: ListMedia::class,
            )
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function applyPersianLabel(object $component): void
    {
        if (! method_exists($component, 'getName') || ! method_exists($component, 'label')) {
            return;
        }

        $labels = [
            'title' => 'عنوان',
            'name' => 'نام',
            'slug' => 'نامک',
            'status' => 'وضعیت',
            'description' => 'توضیحات',
            'content' => 'محتوا',
            'excerpt' => 'خلاصه',
            'template' => 'قالب',
            'blocks' => 'بلاک‌ها',
            'image' => 'تصویر',
            'featured_image' => 'تصویر شاخص',
            'featured_media_id' => 'تصویر شاخص',
            'gallery' => 'گالری',
            'images' => 'تصاویر',
            'category' => 'دسته‌بندی',
            'category_id' => 'دسته‌بندی',
            'product_category_id' => 'دسته محصول',
            'project_category_id' => 'دسته پروژه',
            'gallery_category_id' => 'دسته گالری',
            'project_id' => 'پروژه',
            'type' => 'نوع',
            'price' => 'قیمت',
            'sale_price' => 'قیمت فروش ویژه',
            'sku' => 'شناسه محصول',
            'has_stock' => 'قابل خرید',
            'stock_status' => 'وضعیت موجودی',
            'is_featured' => 'ویژه',
            'sort_order' => 'ترتیب نمایش',
            'published_at' => 'زمان انتشار',
            'updated_at' => 'آخرین به‌روزرسانی',
            'created_at' => 'تاریخ ایجاد',
            'seo_title' => 'عنوان سئو',
            'seo_description' => 'توضیحات سئو',
            'seo_image' => 'تصویر شبکه‌های اجتماعی',
            'seo_keywords' => 'کلمات کلیدی سئو',
            'robots_index' => 'اجازه ایندکس',
            'robots_follow' => 'اجازه دنبال کردن لینک‌ها',
            'url' => 'نشانی',
            'target_url' => 'مقصد ریدایرکت',
            'source_path' => 'مسیر مبدا',
            'status_code' => 'کد وضعیت',
            'is_active' => 'فعال',
            'hits_count' => 'تعداد بازدید',
            'last_hit_at' => 'آخرین بازدید',
            'note' => 'یادداشت',
            'admin_note' => 'یادداشت مدیر',
            'order_number' => 'شماره سفارش',
            'customer_name' => 'نام مشتری',
            'customer_phone' => 'تلفن مشتری',
            'customer_email' => 'ایمیل مشتری',
            'customer_address' => 'آدرس مشتری',
            'payment_status' => 'وضعیت پرداخت',
            'payment_method' => 'روش پرداخت',
            'subtotal' => 'جمع کل',
            'total' => 'مبلغ نهایی',
            'quantity' => 'تعداد',
            'unit_price' => 'قیمت واحد',
            'product_title' => 'عنوان محصول',
            'product_sku' => 'شناسه محصول',
            'email' => 'ایمیل',
            'phone' => 'تلفن',
            'subject' => 'موضوع',
            'message' => 'پیام',
        ];

        $name = $component->getName();
        $name = str_contains($name, '.') ? str($name)->afterLast('.')->toString() : $name;

        if (isset($labels[$name])) {
            $component->label($labels[$name]);
        }

        $stateLabels = [
            'status' => [
                'draft' => 'پیش‌نویس',
                'published' => 'منتشرشده',
                'archived' => 'بایگانی‌شده',
                'active' => 'فعال',
                'inactive' => 'غیرفعال',
                'new' => 'جدید',
                'read' => 'خوانده‌شده',
                'replied' => 'پاسخ‌داده‌شده',
                'pending' => 'در انتظار',
                'paid' => 'پرداخت‌شده',
                'cancelled' => 'لغوشده',
                'completed' => 'تکمیل‌شده',
            ],
            'payment_status' => [
                'unpaid' => 'پرداخت‌نشده',
                'paid' => 'پرداخت‌شده',
                'failed' => 'ناموفق',
            ],
            'stock_status' => [
                'in_stock' => 'موجود',
                'out_of_stock' => 'ناموجود',
            ],
        ];

        if (isset($stateLabels[$name]) && method_exists($component, 'formatStateUsing')) {
            $map = $stateLabels[$name];
            $component->formatStateUsing(fn (?string $state): ?string => $map[$state] ?? $state);
        }

        if (isset($stateLabels[$name]) && method_exists($component, 'options')) {
            $component->options($stateLabels[$name]);
        }
    }
}
