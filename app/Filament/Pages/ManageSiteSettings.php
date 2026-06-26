<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Services\ModuleCleanupService;
use App\Services\ModuleRedirectSuggestionService;
use App\Services\SettingsService;
use App\Support\TemporaryDebugLogger;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSiteSettings extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use UsesMediaLibraryImages;

    private const THEME_SIZE_DEFAULTS = [
        'base_font_size' => '16px',
        'button_font_size' => '16px',
        'h1_font_size' => '24px',
        'h2_font_size' => '22px',
        'h3_font_size' => '20px',
        'h4_font_size' => '18px',
        'button_radius' => '10px',
        'container_width' => '1200px',
        'base_font_size_mobile' => '15px',
        'button_font_size_mobile' => '15px',
        'h1_font_size_mobile' => '22px',
        'h2_font_size_mobile' => '20px',
        'h3_font_size_mobile' => '18px',
        'h4_font_size_mobile' => '16px',
        'button_radius_mobile' => '10px',
        'container_width_mobile' => '343px',
    ];

    protected static ?string $navigationGroup = 'تنظیمات سایت';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'تنظیمات سایت';

    protected static ?string $title = 'تنظیمات سایت';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-site-settings';

    public ?array $data = [];

    private array $settingsMeta = [
        'site_name' => ['general', 'text'],
        'site_description' => ['general', 'textarea'],
        'image_placeholder' => ['general', 'image'],
        'health_check_enabled' => ['general', 'boolean'],
        'site_logo' => ['branding', 'image'],
        'site_favicon' => ['branding', 'image'],
        'contact_phone' => ['contact', 'text'],
        'contact_email' => ['contact', 'text'],
        'contact_address' => ['contact', 'textarea'],
        'social_instagram_url' => ['social', 'text'],
        'social_telegram_url' => ['social', 'text'],
        'social_whatsapp_url' => ['social', 'text'],
        'social_linkedin_url' => ['social', 'text'],
        'social_x_url' => ['social', 'text'],
        'header_cta_label' => ['header', 'text'],
        'header_cta_url' => ['header', 'text'],
        'footer_text' => ['footer', 'textarea'],
        'site_title' => ['seo', 'text'],
        'default_meta_description' => ['seo', 'textarea'],
        'default_og_image' => ['seo', 'image'],
        'robots_disallow' => ['seo', 'text'],
        'robots_txt' => ['seo', 'textarea'],
        'sitemap_enabled' => ['seo', 'boolean'],
        'projects_enabled' => ['projects', 'boolean'],
        'projects_label' => ['projects', 'text'],
        'projects_index_title' => ['projects', 'text'],
        'projects_index_description' => ['projects', 'textarea'],
        'projects_per_page' => ['projects', 'number'],
        'projects_seo_title' => ['projects', 'text'],
        'projects_seo_description' => ['projects', 'textarea'],
        'projects_og_image' => ['projects', 'image'],
        'galleries_enabled' => ['galleries', 'boolean'],
        'galleries_label' => ['galleries', 'text'],
        'galleries_index_title' => ['galleries', 'text'],
        'galleries_index_description' => ['galleries', 'textarea'],
        'galleries_per_page' => ['galleries', 'number'],
        'galleries_seo_title' => ['galleries', 'text'],
        'galleries_seo_description' => ['galleries', 'textarea'],
        'shop_enabled' => ['shop', 'boolean'],
        'shop_label' => ['shop', 'text'],
        'shop_index_title' => ['shop', 'text'],
        'shop_index_description' => ['shop', 'textarea'],
        'shop_per_page' => ['shop', 'number'],
        'shop_seo_title' => ['shop', 'text'],
        'shop_seo_description' => ['shop', 'textarea'],
        'shop_order_admin_email' => ['shop', 'text'],
        'shop_manual_payment_message' => ['shop', 'textarea'],
        'payment_gateway' => ['payment', 'select'],
        'zarinpal_access_token' => ['payment', 'password'],
        'zarinpal_graphql_endpoint' => ['payment', 'text'],
        'zarinpal_callback_url' => ['payment', 'text'],
        'primary_color' => ['theme', 'color'],
        'secondary_color' => ['theme', 'color'],
        'accent_color' => ['theme', 'color'],
        'text_color' => ['theme', 'color'],
        'link_color' => ['theme', 'color'],
        'background_color' => ['theme', 'color'],
        'font_family' => ['theme', 'select'],
        'custom_font_name' => ['theme', 'text'],
        'custom_font_file' => ['theme', 'file'],
        'base_font_size' => ['theme', 'text'],
        'h1_font_size' => ['theme', 'text'],
        'h2_font_size' => ['theme', 'text'],
        'h3_font_size' => ['theme', 'text'],
        'h4_font_size' => ['theme', 'text'],
        'button_font_size' => ['theme', 'text'],
        'base_font_size_mobile' => ['theme', 'text'],
        'h1_font_size_mobile' => ['theme', 'text'],
        'h2_font_size_mobile' => ['theme', 'text'],
        'h3_font_size_mobile' => ['theme', 'text'],
        'h4_font_size_mobile' => ['theme', 'text'],
        'button_font_size_mobile' => ['theme', 'text'],
        'button_radius_mobile' => ['theme', 'text'],
        'container_width_mobile' => ['theme', 'text'],
        'button_radius' => ['theme', 'text'],
        'container_width' => ['theme', 'text'],
    ];

    public function mount(SettingsService $settings): void
    {
        $state = $settings->many(array_keys($this->settingsMeta))->all();

        foreach (self::THEME_SIZE_DEFAULTS as $key => $default) {
            if (blank($state[$key] ?? null)) {
                $state[$key] = $default;
            }
        }

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('تنظیمات')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('عمومی')
                            ->schema([
                                Forms\Components\TextInput::make('site_name')
                                    ->label('نام سایت')
                                    ->placeholder('مثلا نور')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('site_description')
                                    ->label('توضیح کوتاه سایت')
                                    ->placeholder('توضیحی کوتاه درباره سایت وارد کنید')
                                    ->rows(3),
                                Forms\Components\ViewField::make('image_placeholder')
                                    ->label('تصویر پیش‌فرض سایت')
                                    ->view('filament.forms.components.media-library-url-picker')
                                    ->viewData(fn (): array => [
                                        'images' => static::mediaLibraryImageItems(),
                                    ])
                                    ->helperText('اگر در بلوک‌ها تصویری انتخاب نشده باشد، این تصویر به‌عنوان جایگزین در پیش‌نمایش فرم نمایش داده می‌شود.')
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('health_check_enabled')
                                    ->label('فعال‌سازی بررسی سلامت عمومی')
                                    ->default(true)
                                    ->helperText('دسترسی امن به مسیر /health را برای سرویس‌های پایش وضعیت سایت کنترل می‌کند.'),
                            ]),
                        Forms\Components\Tabs\Tab::make('برندسازی')
                            ->schema([
                                Forms\Components\FileUpload::make('site_logo')
                                    ->label('لوگوی سایت')
                                    ->disk('public')
                                    ->directory('settings')
                                    ->visibility('public')
                                    ->image()
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('site_favicon')
                                    ->label('آیکون سایت')
                                    ->disk('public')
                                    ->directory('settings')
                                    ->visibility('public')
                                    ->image(),
                            ]),
                        Forms\Components\Tabs\Tab::make('تماس')
                            ->schema([
                                Forms\Components\TextInput::make('contact_phone')
                                    ->label('شماره تماس')
                                    ->placeholder('مثلا 02112345678')
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('contact_email')
                                    ->label('ایمیل تماس')
                                    ->placeholder('مثلا info@example.com')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('contact_address')
                                    ->label('آدرس')
                                    ->placeholder('آدرس کامل کسب‌وکار را وارد کنید')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('شبکه‌های اجتماعی')
                            ->schema([
                                Forms\Components\TextInput::make('social_instagram_url')->label('لینک اینستاگرام')->placeholder('https://instagram.com/...')->url()->maxLength(255),
                                Forms\Components\TextInput::make('social_telegram_url')->label('لینک تلگرام')->placeholder('https://t.me/...')->url()->maxLength(255),
                                Forms\Components\TextInput::make('social_whatsapp_url')->label('لینک واتساپ')->placeholder('https://wa.me/...')->url()->maxLength(255),
                                Forms\Components\TextInput::make('social_linkedin_url')->label('لینک لینکدین')->placeholder('https://linkedin.com/company/...')->url()->maxLength(255),
                                Forms\Components\TextInput::make('social_x_url')->label('لینک ایکس / توییتر')->placeholder('https://x.com/...')->url()->maxLength(255),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('هدر')
                            ->schema([
                                Forms\Components\TextInput::make('header_cta_label')
                                    ->label('متن دکمه هدر')
                                    ->placeholder('مثلا تماس با ما')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('header_cta_url')
                                    ->label('لینک دکمه هدر')
                                    ->placeholder('مثلا /contact')
                                    ->maxLength(255),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('فوتر')
                            ->schema([
                                Forms\Components\Textarea::make('footer_text')
                                    ->label('متن فوتر')
                                    ->placeholder('متن کوتاهی که در فوتر نمایش داده می‌شود')
                                    ->rows(4)
                                    ->helperText('این متن به‌صورت اختیاری در فوتر سایت نمایش داده می‌شود.'),
                            ]),
                        Forms\Components\Tabs\Tab::make('سئو')
                            ->schema([
                                Forms\Components\TextInput::make('site_title')
                                    ->label('عنوان پیش‌فرض سئو')
                                    ->placeholder('عنوانی که در نتایج جستجو نمایش داده می‌شود')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('default_meta_description')
                                    ->label('توضیحات پیش‌فرض سئو')
                                    ->placeholder('توضیح پیش‌فرض صفحات برای موتورهای جستجو')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('default_og_image')
                                    ->label('تصویر پیش‌فرض شبکه‌های اجتماعی')
                                    ->disk('public')
                                    ->directory('settings')
                                    ->visibility('public')
                                    ->image()
                                    ->imageEditor()
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('robots_disallow')
                                    ->label('مسیر ممنوع برای ربات‌ها')
                                    ->placeholder('مثلا /private'),
                                Forms\Components\Textarea::make('robots_txt')
                                    ->label('محتوای سفارشی robots.txt')
                                    ->placeholder('دستورهای robots.txt را وارد کنید')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('sitemap_enabled')
                                    ->label('فعال‌سازی سایت‌مپ')
                                    ->default(true),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('پروژه‌ها')
                            ->schema([
                                Forms\Components\Placeholder::make('projects_module_note')
                                    ->label('نمایش ماژول')
                                    ->content('غیرفعال کردن پروژه‌ها لینک‌ها و منوی مدیریت پروژه را پنهان می‌کند، مسیرهای عمومی پروژه 404 می‌شوند و داده‌های موجود حفظ می‌شوند. پاکسازی داده‌ها جداگانه و غیرقابل بازگشت است.'),
                                Forms\Components\Toggle::make('projects_enabled')
                                    ->label('فعال‌سازی پروژه‌ها')
                                    ->default(true)
                                    ->live(),
                                Forms\Components\Group::make([
                                    Forms\Components\TextInput::make('projects_label')
                                        ->label('عنوان ماژول پروژه‌ها')
                                        ->placeholder('مثلا پروژه‌ها')
                                        ->maxLength(255)
                                        ->default('پروژه‌ها'),
                                    Forms\Components\TextInput::make('projects_index_title')
                                        ->label('عنوان صفحه فهرست پروژه‌ها')
                                        ->placeholder('مثلا پروژه‌های ما')
                                        ->maxLength(255)
                                        ->default('پروژه‌ها'),
                                    Forms\Components\TextInput::make('projects_per_page')
                                        ->label('تعداد پروژه در هر صفحه')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(48)
                                        ->default(12),
                                    Forms\Components\Textarea::make('projects_index_description')
                                        ->label('توضیحات صفحه فهرست پروژه‌ها')
                                        ->placeholder('متن معرفی صفحه پروژه‌ها را وارد کنید')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('projects_seo_title')
                                        ->label('عنوان سئوی پروژه‌ها')
                                        ->placeholder('عنوان سئو برای صفحه پروژه‌ها')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('projects_seo_description')
                                        ->label('توضیحات سئوی پروژه‌ها')
                                        ->placeholder('توضیحات سئو برای صفحه پروژه‌ها')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\FileUpload::make('projects_og_image')
                                        ->label('تصویر شبکه‌های اجتماعی پروژه‌ها')
                                        ->disk('public')
                                        ->directory('settings')
                                        ->visibility('public')
                                        ->image()
                                        ->imageEditor()
                                        ->columnSpanFull(),
                                ])
                                    ->hidden(fn (Forms\Get $get): bool => ! (bool) $get('projects_enabled'))
                                    ->columns(2)
                                    ->columnSpanFull(),
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('createProjectRedirects')
                                        ->label('ساخت ریدایرکت‌های پروژه')
                                        ->icon('heroicon-o-arrow-path-rounded-square')
                                        ->color('warning')
                                        ->form([
                                            Forms\Components\TextInput::make('target_url')
                                                ->label('مقصد ریدایرکت')
                                                ->placeholder('مثلا / یا /services')
                                                ->default('/')
                                                ->required()
                                                ->helperText('مقصد پیش‌فرض برای آدرس‌های غیرفعال پروژه، مثلا / یا /services.'),
                                            Forms\Components\Select::make('status_code')
                                                ->label('کد وضعیت')
                                                ->options([301 => '301 دائمی', 302 => '302 موقت'])
                                                ->default(301)
                                                ->required(),
                                        ])
                                        ->requiresConfirmation()
                                        ->modalHeading('ریدایرکت‌های آدرس‌های پروژه ساخته شوند؟')
                                        ->modalDescription('برای /projects، دسته‌بندی‌های پروژه و صفحه جزئیات پروژه ریدایرکت فعال می‌سازد. این کار ماژول را غیرفعال نمی‌کند و داده‌ای را حذف نمی‌کند.')
                                        ->modalSubmitActionLabel('ساخت ریدایرکت‌ها')
                                        ->action(fn (array $data) => $this->createProjectRedirects($data, app(ModuleRedirectSuggestionService::class))),
                                    Forms\Components\Actions\Action::make('cleanupProjects')
                                        ->label('حذف داده‌های پروژه')
                                        ->color('danger')
                                        ->icon('heroicon-o-trash')
                                        ->requiresConfirmation()
                                        ->modalHeading('همه پروژه‌ها و دسته‌بندی‌های پروژه حذف شوند؟')
                                        ->modalDescription('همه رکوردهای پروژه و دسته‌بندی‌های پروژه برای همیشه حذف می‌شوند. فایل‌های کتابخانه رسانه حذف نمی‌شوند. غیرفعال کردن ماژول به‌تنهایی هیچ داده‌ای را حذف نمی‌کند.')
                                        ->modalSubmitActionLabel('حذف داده‌های پروژه')
                                        ->action(fn () => $this->cleanupProjects(app(ModuleCleanupService::class))),
                                ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('گالری‌ها')
                            ->schema([
                                Forms\Components\Placeholder::make('galleries_module_note')
                                    ->label('نمایش ماژول')
                                    ->content('غیرفعال کردن گالری‌ها لینک‌ها و منوی مدیریت گالری را پنهان می‌کند، مسیرهای عمومی گالری 404 می‌شوند و داده‌های موجود حفظ می‌شوند. پاکسازی داده‌ها جداگانه و غیرقابل بازگشت است.'),
                                Forms\Components\Toggle::make('galleries_enabled')
                                    ->label('فعال‌سازی گالری‌ها')
                                    ->default(true)
                                    ->live(),
                                Forms\Components\Group::make([
                                    Forms\Components\TextInput::make('galleries_label')
                                        ->label('عنوان ماژول گالری‌ها')
                                        ->placeholder('مثلا گالری‌ها')
                                        ->maxLength(255)
                                        ->default('گالری‌ها'),
                                    Forms\Components\TextInput::make('galleries_index_title')
                                        ->label('عنوان صفحه فهرست گالری‌ها')
                                        ->placeholder('مثلا گالری تصاویر')
                                        ->maxLength(255)
                                        ->default('گالری‌ها'),
                                    Forms\Components\TextInput::make('galleries_per_page')
                                        ->label('تعداد گالری در هر صفحه')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(48)
                                        ->default(12),
                                    Forms\Components\Textarea::make('galleries_index_description')
                                        ->label('توضیحات صفحه فهرست گالری‌ها')
                                        ->placeholder('متن معرفی صفحه گالری‌ها را وارد کنید')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('galleries_seo_title')
                                        ->label('عنوان سئوی گالری‌ها')
                                        ->placeholder('عنوان سئو برای صفحه گالری‌ها')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('galleries_seo_description')
                                        ->label('توضیحات سئوی گالری‌ها')
                                        ->placeholder('توضیحات سئو برای صفحه گالری‌ها')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                    ->hidden(fn (Forms\Get $get): bool => ! (bool) $get('galleries_enabled'))
                                    ->columns(2)
                                    ->columnSpanFull(),
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('createGalleryRedirects')
                                        ->label('ساخت ریدایرکت‌های گالری')
                                        ->icon('heroicon-o-arrow-path-rounded-square')
                                        ->color('warning')
                                        ->form([
                                            Forms\Components\TextInput::make('target_url')
                                                ->label('مقصد ریدایرکت')
                                                ->placeholder('مثلا / یا /projects')
                                                ->default('/')
                                                ->required()
                                                ->helperText('مقصد پیش‌فرض برای آدرس‌های غیرفعال گالری، مثلا / یا /projects.'),
                                            Forms\Components\Select::make('status_code')
                                                ->label('کد وضعیت')
                                                ->options([301 => '301 دائمی', 302 => '302 موقت'])
                                                ->default(301)
                                                ->required(),
                                        ])
                                        ->requiresConfirmation()
                                        ->modalHeading('ریدایرکت‌های آدرس‌های گالری ساخته شوند؟')
                                        ->modalDescription('برای /galleries، دسته‌بندی‌های گالری و صفحه جزئیات گالری ریدایرکت فعال می‌سازد. این کار ماژول را غیرفعال نمی‌کند و داده‌ای را حذف نمی‌کند.')
                                        ->modalSubmitActionLabel('ساخت ریدایرکت‌ها')
                                        ->action(fn (array $data) => $this->createGalleryRedirects($data, app(ModuleRedirectSuggestionService::class))),
                                    Forms\Components\Actions\Action::make('cleanupGalleries')
                                        ->label('حذف داده‌های گالری')
                                        ->color('danger')
                                        ->icon('heroicon-o-trash')
                                        ->requiresConfirmation()
                                        ->modalHeading('همه گالری‌ها و دسته‌بندی‌های گالری حذف شوند؟')
                                        ->modalDescription('همه رکوردهای گالری و دسته‌بندی‌های گالری برای همیشه حذف می‌شوند. فایل‌های کتابخانه رسانه حذف نمی‌شوند. غیرفعال کردن ماژول به‌تنهایی هیچ داده‌ای را حذف نمی‌کند.')
                                        ->modalSubmitActionLabel('حذف داده‌های گالری')
                                        ->action(fn () => $this->cleanupGalleries(app(ModuleCleanupService::class))),
                                ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('فروشگاه')
                            ->schema([
                                Forms\Components\Placeholder::make('shop_module_note')
                                    ->label('نمایش ماژول')
                                    ->content('غیرفعال کردن فروشگاه لینک‌های فروشگاه، سبد خرید، تسویه‌حساب و منوی مدیریت را پنهان می‌کند، مسیرهای عمومی فروشگاه 404 می‌شوند و داده‌های موجود حفظ می‌شوند. پاکسازی داده‌ها جداگانه و غیرقابل بازگشت است.'),
                                Forms\Components\Toggle::make('shop_enabled')
                                    ->label('فعال‌سازی فروشگاه')
                                    ->default(true)
                                    ->live(),
                                Forms\Components\Group::make([
                                    Forms\Components\TextInput::make('shop_label')
                                        ->label('عنوان ماژول فروشگاه')
                                        ->placeholder('مثلا فروشگاه')
                                        ->maxLength(255)
                                        ->default('فروشگاه'),
                                    Forms\Components\TextInput::make('shop_index_title')
                                        ->label('عنوان صفحه فروشگاه')
                                        ->placeholder('مثلا محصولات')
                                        ->maxLength(255)
                                        ->default('فروشگاه'),
                                    Forms\Components\TextInput::make('shop_per_page')
                                        ->label('تعداد محصول در هر صفحه')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(48)
                                        ->default(12),
                                    Forms\Components\Textarea::make('shop_index_description')
                                        ->label('توضیحات صفحه فروشگاه')
                                        ->placeholder('متن معرفی صفحه فروشگاه را وارد کنید')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('shop_seo_title')
                                        ->label('عنوان سئوی فروشگاه')
                                        ->placeholder('عنوان سئو برای صفحه فروشگاه')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('shop_seo_description')
                                        ->label('توضیحات سئوی فروشگاه')
                                        ->placeholder('توضیحات سئو برای صفحه فروشگاه')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('shop_order_admin_email')
                                        ->label('ایمیل دریافت اعلان سفارش')
                                        ->placeholder('مثلا orders@example.com')
                                        ->email()
                                        ->maxLength(255)
                                        ->helperText('اگر خالی باشد، ایمیل تماس سایت استفاده می‌شود.'),
                                    Forms\Components\Textarea::make('shop_manual_payment_message')
                                        ->label('پیام پرداخت دستی')
                                        ->placeholder('متنی که پس از ثبت سفارش به مشتری نمایش داده می‌شود')
                                        ->rows(3)
                                        ->helperText('در صفحه تشکر و ایمیل تایید سفارش مشتری نمایش داده می‌شود.')
                                        ->columnSpanFull(),
                                    Forms\Components\Select::make('payment_gateway')
                                        ->label('درگاه پرداخت')
                                        ->options([
                                            'manual' => 'پرداخت دستی',
                                            'zarinpal' => 'زرین‌پال (فقط ساختار)',
                                        ])
                                        ->default('manual')
                                        ->helperText('پرداخت دستی گزینه پیش‌فرض امن است. زرین‌پال قبل از پرداخت واقعی به جزئیات رسمی اتصال نیاز دارد.'),
                                    Forms\Components\TextInput::make('zarinpal_access_token')
                                        ->label('توکن دسترسی زرین‌پال')
                                        ->placeholder('توکن دریافتی از زرین‌پال')
                                        ->password()
                                        ->revealable()
                                        ->maxLength(1000)
                                        ->helperText('در تنظیمات ذخیره می‌شود. این مقدار را به‌صورت عمومی نمایش ندهید.'),
                                    Forms\Components\TextInput::make('zarinpal_graphql_endpoint')
                                        ->label('آدرس گراف‌کیوال زرین‌پال')
                                        ->placeholder('https://next.zarinpal.com/api/v4/graphql/')
                                        ->url()
                                        ->maxLength(255)
                                        ->default('https://next.zarinpal.com/api/v4/graphql/'),
                                    Forms\Components\TextInput::make('zarinpal_callback_url')
                                        ->label('آدرس بازگشت زرین‌پال')
                                        ->placeholder('مثلا /payments/zarinpal/callback')
                                        ->url()
                                        ->maxLength(255)
                                        ->helperText('اختیاری است. مقدار پیش‌فرض /payments/zarinpal/callback است.'),
                                ])
                                    ->hidden(fn (Forms\Get $get): bool => ! (bool) $get('shop_enabled'))
                                    ->columns(2)
                                    ->columnSpanFull(),
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('createShopRedirects')
                                        ->label('ساخت ریدایرکت‌های فروشگاه')
                                        ->icon('heroicon-o-arrow-path-rounded-square')
                                        ->color('warning')
                                        ->form([
                                            Forms\Components\TextInput::make('target_url')
                                                ->label('مقصد ریدایرکت')
                                                ->placeholder('مثلا / یا /contact')
                                                ->default('/')
                                                ->required()
                                                ->helperText('مقصد پیش‌فرض برای آدرس‌های غیرفعال فروشگاه، سبد خرید و تسویه‌حساب، مثلا / یا /contact.'),
                                            Forms\Components\Select::make('status_code')
                                                ->label('کد وضعیت')
                                                ->options([301 => '301 دائمی', 302 => '302 موقت'])
                                                ->default(301)
                                                ->required(),
                                        ])
                                        ->requiresConfirmation()
                                        ->modalHeading('ریدایرکت‌های آدرس‌های فروشگاه ساخته شوند؟')
                                        ->modalDescription('برای /shop، دسته‌بندی‌های محصول، صفحه جزئیات محصول، /cart و /checkout ریدایرکت فعال می‌سازد. این کار ماژول را غیرفعال نمی‌کند و داده‌ای را حذف نمی‌کند.')
                                        ->modalSubmitActionLabel('ساخت ریدایرکت‌ها')
                                        ->action(fn (array $data) => $this->createShopRedirects($data, app(ModuleRedirectSuggestionService::class))),
                                    Forms\Components\Actions\Action::make('cleanupShop')
                                        ->label('حذف داده‌های فروشگاه')
                                        ->color('danger')
                                        ->icon('heroicon-o-trash')
                                        ->requiresConfirmation()
                                        ->modalHeading('همه داده‌های فروشگاه حذف شوند؟')
                                        ->modalDescription('محصولات، دسته‌بندی‌های محصول، سفارش‌ها و آیتم‌های سفارش برای همیشه حذف می‌شوند. فایل‌های کتابخانه رسانه حذف نمی‌شوند. غیرفعال کردن ماژول به‌تنهایی هیچ داده‌ای را حذف نمی‌کند.')
                                        ->modalSubmitActionLabel('حذف داده‌های فروشگاه')
                                        ->action(fn () => $this->cleanupShop(app(ModuleCleanupService::class))),
                                ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('قالب ظاهری')
                            ->schema($this->themeTabSchema())
                            ->columns(1),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    private function themeTabSchema(): array
    {
        return [
            Forms\Components\Grid::make([
                'default' => 1,
                'lg' => 5,
            ])
                ->schema([
                    Forms\Components\Group::make([
                        Forms\Components\Section::make('رنگ و فونت')
                            ->schema([
                                Forms\Components\ColorPicker::make('primary_color')
                                    ->label('رنگ اصلی')
                                    ->default('#2563eb')
                                    ->live(),
                                Forms\Components\ColorPicker::make('secondary_color')
                                    ->label('رنگ ثانویه')
                                    ->default('#111827')
                                    ->live(),
                                Forms\Components\ColorPicker::make('accent_color')
                                    ->label('رنگ تاکیدی')
                                    ->default('#0f766e')
                                    ->live(),
                                Forms\Components\ColorPicker::make('text_color')
                                    ->label('رنگ متن')
                                    ->default('#1f2937')
                                    ->live(),
                                Forms\Components\ColorPicker::make('link_color')
                                    ->label('رنگ لینک‌های متن')
                                    ->default('#2563eb')
                                    ->live(),
                                Forms\Components\ColorPicker::make('background_color')
                                    ->label('رنگ پس‌زمینه')
                                    ->default('#f8fafc')
                                    ->live(),
                                Forms\Components\Select::make('font_family')
                                    ->label('خانواده فونت')
                                    ->options([
                                        'system' => 'فونت پیش‌فرض سیستم',
                                        'serif' => 'سریف',
                                        'mono' => 'تک‌فاصله',
                                        'custom' => 'فونت سفارشی آپلودشده',
                                    ])
                                    ->default('system')
                                    ->live(),
                                Forms\Components\TextInput::make('custom_font_name')
                                    ->label('نام فونت سفارشی')
                                    ->placeholder('مثلا NoorFont')
                                    ->maxLength(80)
                                    ->default('فونت سفارشی سایت')
                                    ->live(debounce: 300)
                                    ->visible(fn (Forms\Get $get): bool => $get('font_family') === 'custom'),
                                Forms\Components\FileUpload::make('custom_font_file')
                                    ->label('فایل فونت سفارشی')
                                    ->disk('public')
                                    ->directory('settings/fonts')
                                    ->visibility('public')
                                    ->acceptedFileTypes([
                                        '.woff',
                                        '.woff2',
                                        '.ttf',
                                        '.otf',
                                        'font/woff',
                                        'font/woff2',
                                        'font/ttf',
                                        'font/otf',
                                        'application/font-woff',
                                        'application/x-font-ttf',
                                        'application/x-font-opentype',
                                        'application/octet-stream',
                                    ])
                                    ->maxSize(4096)
                                    ->helperText('در صورت امکان WOFF2 آپلود کنید.')
                                    ->visible(fn (Forms\Get $get): bool => $get('font_family') === 'custom')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Section::make('سایزها')
                            ->schema([
                                Forms\Components\ToggleButtons::make('theme_size_device')
                                    ->label('حالت سایز')
                                    ->options([
                                        'desktop' => 'دسکتاپ',
                                        'mobile' => 'موبایل',
                                    ])
                                    ->icons([
                                        'desktop' => 'heroicon-o-computer-desktop',
                                        'mobile' => 'heroicon-o-device-phone-mobile',
                                    ])
                                    ->default('desktop')
                                    ->live()
                                    ->dehydrated(false)
                                    ->inline()
                                    ->grouped()
                                    ->hiddenButtonLabels()
                                    ->columnSpanFull(),
                                $this->fontSizeControl('base_font_size', 'متن‌ها', self::THEME_SIZE_DEFAULTS['base_font_size'], 'desktop'),
                                $this->fontSizeControl('button_font_size', 'دکمه‌ها', self::THEME_SIZE_DEFAULTS['button_font_size'], 'desktop'),
                                $this->fontSizeControl('h1_font_size', 'H1', self::THEME_SIZE_DEFAULTS['h1_font_size'], 'desktop'),
                                $this->fontSizeControl('h2_font_size', 'H2', self::THEME_SIZE_DEFAULTS['h2_font_size'], 'desktop'),
                                $this->fontSizeControl('h3_font_size', 'H3', self::THEME_SIZE_DEFAULTS['h3_font_size'], 'desktop'),
                                $this->fontSizeControl('h4_font_size', 'H4', self::THEME_SIZE_DEFAULTS['h4_font_size'], 'desktop'),
                                $this->fontSizeControl('button_radius', 'گردی دکمه‌ها', self::THEME_SIZE_DEFAULTS['button_radius'], 'desktop'),
                                $this->fontSizeControl('container_width', 'عرض محتوا', self::THEME_SIZE_DEFAULTS['container_width'], 'desktop'),
                                $this->fontSizeControl('base_font_size_mobile', 'متن‌ها', self::THEME_SIZE_DEFAULTS['base_font_size_mobile'], 'mobile'),
                                $this->fontSizeControl('button_font_size_mobile', 'دکمه‌ها', self::THEME_SIZE_DEFAULTS['button_font_size_mobile'], 'mobile'),
                                $this->fontSizeControl('h1_font_size_mobile', 'H1', self::THEME_SIZE_DEFAULTS['h1_font_size_mobile'], 'mobile'),
                                $this->fontSizeControl('h2_font_size_mobile', 'H2', self::THEME_SIZE_DEFAULTS['h2_font_size_mobile'], 'mobile'),
                                $this->fontSizeControl('h3_font_size_mobile', 'H3', self::THEME_SIZE_DEFAULTS['h3_font_size_mobile'], 'mobile'),
                                $this->fontSizeControl('h4_font_size_mobile', 'H4', self::THEME_SIZE_DEFAULTS['h4_font_size_mobile'], 'mobile'),
                                $this->fontSizeControl('button_radius_mobile', 'گردی دکمه‌ها', self::THEME_SIZE_DEFAULTS['button_radius_mobile'], 'mobile'),
                                $this->fontSizeControl('container_width_mobile', 'عرض محتوا', self::THEME_SIZE_DEFAULTS['container_width_mobile'], 'mobile'),
                            ])
                            ->columns(2),
                    ])
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 3,
                        ]),
                    Forms\Components\ViewField::make('theme_live_preview')
                        ->label('پیش‌نمایش زنده')
                        ->view('filament.forms.components.theme-live-preview')
                        ->dehydrated(false)
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 2,
                        ]),
                ]),
        ];
    }

    private function fontSizeControl(string $field, string $label, string $default, string $device): Forms\Components\Grid
    {
        return Forms\Components\Grid::make(4)
            ->schema([
                Forms\Components\TextInput::make($field)
                    ->label($label)
                    ->default($default)
                    ->live(debounce: 300)
                    ->columnSpan(3),
                Forms\Components\Select::make($field.'_unit')
                    ->label('واحد')
                    ->options($this->cssUnitOptions())
                    ->default(fn (Forms\Get $get): string => $this->cssUnit((string) ($get($field) ?: $default)))
                    ->afterStateHydrated(function (Forms\Components\Select $component, Forms\Get $get) use ($field, $default): void {
                        $component->state($this->cssUnit((string) ($get($field) ?: $default)));
                    })
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) use ($field, $default): void {
                        $set($field, $this->replaceCssUnit((string) ($get($field) ?: $default), $state ?: 'px', $default));
                    })
                    ->columnSpan(1),
            ])
            ->visible(fn (Forms\Get $get): bool => ($get('theme_size_device') ?? 'desktop') === $device);
    }

    private function cssUnitOptions(): array
    {
        return [
            'px' => 'px',
            'rem' => 'rem',
            'em' => 'em',
            '%' => '%',
            'vw' => 'vw',
        ];
    }

    private function cssUnit(string $value): string
    {
        preg_match('/(px|rem|em|%|vw)$/', trim($value), $matches);

        return $matches[1] ?? 'px';
    }

    private function replaceCssUnit(string $value, string $unit, string $default): string
    {
        $value = trim($value) ?: $default;
        $number = preg_replace('/[^0-9.]/', '', $value);

        if ($number === '' || ! is_numeric($number)) {
            $number = preg_replace('/[^0-9.]/', '', $default) ?: '16';
        }

        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        if (str_starts_with($number, '.')) {
            $number = '0'.$number;
        }

        return $number.$unit;
    }

    public function save(SettingsService $settings): void
    {
        $state = $this->form->getState();

        // TEMP DEBUG - remove after production save issue is fixed.
        TemporaryDebugLogger::settingsSaveStarted($state);

        try {
            foreach ($this->settingsMeta as $key => [$group, $type]) {
                $settings->set($key, $this->normalizeValue($state[$key] ?? null), $group, $type);
            }

            // TEMP DEBUG - remove after production save issue is fixed.
            TemporaryDebugLogger::settingsSaveCompleted($state);
        } catch (\Throwable $exception) {
            // TEMP DEBUG - remove after production save issue is fixed.
            TemporaryDebugLogger::logException('TEMP DEBUG - Filament settings save failed', $exception, null, [
                'action' => 'settings-save',
                'model_class' => 'App\\Models\\Setting',
                'payload' => TemporaryDebugLogger::payloadSummary($state),
                'large_field_lengths' => TemporaryDebugLogger::largeFieldLengths($state),
                'save_failed' => true,
            ]);

            throw $exception;
        }

        Notification::make()
            ->title('تنظیمات ذخیره شد')
            ->success()
            ->send();
    }

    public function cleanupProjects(ModuleCleanupService $cleanup): void
    {
        $counts = $cleanup->deleteProjects();

        Notification::make()
            ->title('داده‌های پروژه حذف شد')
            ->body("{$counts['projects']} پروژه و {$counts['project_categories']} دسته‌بندی پروژه حذف شد.")
            ->danger()
            ->send();
    }

    public function cleanupShop(ModuleCleanupService $cleanup): void
    {
        $counts = $cleanup->deleteShop();

        Notification::make()
            ->title('داده‌های فروشگاه حذف شد')
            ->body("{$counts['products']} محصول، {$counts['product_categories']} دسته‌بندی محصول، {$counts['orders']} سفارش و {$counts['order_items']} آیتم سفارش حذف شد.")
            ->danger()
            ->send();
    }

    public function cleanupGalleries(ModuleCleanupService $cleanup): void
    {
        $counts = $cleanup->deleteGalleries();

        Notification::make()
            ->title('داده‌های گالری حذف شد')
            ->body("{$counts['galleries']} گالری و {$counts['gallery_categories']} دسته‌بندی گالری حذف شد.")
            ->danger()
            ->send();
    }

    public function createProjectRedirects(array $data, ModuleRedirectSuggestionService $suggestions): void
    {
        $count = $suggestions->createProjectRedirects($data['target_url'] ?? '/', (int) ($data['status_code'] ?? 301));

        Notification::make()
            ->title('ریدایرکت‌های پروژه ساخته شد')
            ->body("{$count} ریدایرکت پروژه ساخته یا به‌روزرسانی شد.")
            ->success()
            ->send();
    }

    public function createShopRedirects(array $data, ModuleRedirectSuggestionService $suggestions): void
    {
        $count = $suggestions->createShopRedirects($data['target_url'] ?? '/', (int) ($data['status_code'] ?? 301));

        Notification::make()
            ->title('ریدایرکت‌های فروشگاه ساخته شد')
            ->body("{$count} ریدایرکت فروشگاه ساخته یا به‌روزرسانی شد.")
            ->success()
            ->send();
    }

    public function createGalleryRedirects(array $data, ModuleRedirectSuggestionService $suggestions): void
    {
        $count = $suggestions->createGalleryRedirects($data['target_url'] ?? '/', (int) ($data['status_code'] ?? 301));

        Notification::make()
            ->title('ریدایرکت‌های گالری ساخته شد')
            ->body("{$count} ریدایرکت گالری ساخته یا به‌روزرسانی شد.")
            ->success()
            ->send();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)->filter()->first();
        }

        return $value;
    }
}
