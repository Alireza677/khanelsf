<?php

namespace App\Filament\Resources;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\PageResource\Pages;
use App\Models\GalleryCategory;
use App\Models\Page;
use App\Models\Post;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = Page::class;

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $startedAt = hrtime(true);

        return tap($form->schema([
            Forms\Components\Tabs::make('ویرایشگر برگه')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('محتوا')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('عنوان')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('slug'))
                                    ? $set('slug', Str::slug($state ?? ''))
                                    : null),
                            Forms\Components\TextInput::make('slug')
                                ->label('نامک')
                                ->required()
                                ->maxLength(255)
                                ->helperText('برای صفحه اصلی قابل ویرایش از "home" استفاده کنید.')
                                ->unique(ignoreRecord: true),
                            Forms\Components\Select::make('template')
                                ->label('قالب')
                                ->required()
                                ->options([
                                    'default' => 'پیش‌فرض',
                                    'home' => 'صفحه اصلی',
                                    'landing' => 'لندینگ',
                                ])
                                ->default('default'),
                            Forms\Components\RichEditor::make('content')
                                ->label('متن محتوا')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('بلوک‌ها')
                        ->schema([
                            Forms\Components\Builder::make('blocks')
                                ->label('بلوک‌های برگه')
                                ->cloneable()
                                ->blocks([
                                    app(BlockRegistry::class)->find('hero')->filamentBlock(HeroBlock::CONTEXT_PAGE),
                                    Forms\Components\Builder\Block::make('cta')
                                        ->label('دعوت به اقدام')
                                        ->icon('heroicon-o-megaphone')
                                        ->schema(static::ctaFields())
                                        ->columns(2),
                                    Forms\Components\Builder\Block::make('feature_grid')
                                        ->label('شبکه ویژگی‌ها')
                                        ->icon('heroicon-o-squares-2x2')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->required()
                                                ->maxLength(255),
                                            static::headingTagField(),
                                            Forms\Components\Textarea::make('section_description')
                                                ->label('توضیحات بخش')
                                                ->rows(3)
                                                ->columnSpanFull(),
                                            Forms\Components\Select::make('items_mode')
                                                ->label('نوع آیتم‌ها')
                                                ->options([
                                                    'static' => 'ثابت',
                                                    'dynamic' => 'داینامیک',
                                                ])
                                                ->default('static')
                                                ->live()
                                                ->required(),
                                            Forms\Components\Select::make('dynamic_source')
                                                ->label('منبع داینامیک')
                                                ->options([
                                                    'posts' => 'آخرین نوشته‌ها',
                                                    'projects' => 'آخرین پروژه‌ها',
                                                ])
                                                ->default('posts')
                                                ->live()
                                                ->afterStateUpdated(fn (Set $set) => $set('dynamic_button_overrides', []))
                                                ->required()
                                                ->visible(fn (Get $get): bool => $get('items_mode') === 'dynamic'),
                                            Forms\Components\TextInput::make('dynamic_rows')
                                                ->label('تعداد ردیف')
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(6)
                                                ->default(1)
                                                ->required()
                                                ->visible(fn (Get $get): bool => $get('items_mode') === 'dynamic'),
                                            Forms\Components\TextInput::make('dynamic_columns')
                                                ->label('تعداد ستون درخواستی')
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(12)
                                                ->default(3)
                                                ->required()
                                                ->visible(fn (Get $get): bool => $get('items_mode') === 'dynamic'),
                                            Forms\Components\TextInput::make('dynamic_grid_width')
                                                ->label('عرض شبکه')
                                                ->numeric()
                                                ->minValue(240)
                                                ->maxValue(2400)
                                                ->default(1180)
                                                ->suffix('px')
                                                ->required()
                                                ->visible(fn (Get $get): bool => $get('items_mode') === 'dynamic'),
                                            Forms\Components\TextInput::make('dynamic_item_width')
                                                ->label('حداقل عرض هر آیتم')
                                                ->numeric()
                                                ->minValue(120)
                                                ->maxValue(800)
                                                ->default(280)
                                                ->suffix('px')
                                                ->required()
                                                ->visible(fn (Get $get): bool => $get('items_mode') === 'dynamic'),
                                            Forms\Components\TextInput::make('dynamic_button_label')
                                                ->label('متن پیش‌فرض دکمه')
                                                ->default('مشاهده بیشتر')
                                                ->maxLength(255)
                                                ->visible(fn (Get $get): bool => $get('items_mode') === 'dynamic'),
                                            Forms\Components\Repeater::make('dynamic_button_overrides')
                                                ->label('متن دکمه اختصاصی')
                                                ->cloneable()
                                                ->schema([
                                                    Forms\Components\Select::make('record_id')
                                                        ->label('نوشته / پروژه')
                                                        ->options(fn (Get $get): array => $get('../../dynamic_source') === 'projects'
                                                            ? Project::query()->published()->latest('published_at')->pluck('title', 'id')->all()
                                                            : Post::query()->published()->latest('published_at')->pluck('title', 'id')->all())
                                                        ->searchable()
                                                        ->required(),
                                                    Forms\Components\TextInput::make('button_label')
                                                        ->label('متن دکمه')
                                                        ->required()
                                                        ->maxLength(255),
                                                ])
                                                ->defaultItems(0)
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->collapsible()
                                                ->visible(fn (Get $get): bool => $get('items_mode') === 'dynamic'),
                                            Forms\Components\Repeater::make('items')
                                                ->label('آیتم‌ها')
                                                ->cloneable()
                                                ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'آیتم')
                                                ->schema([
                                                    Forms\Components\TextInput::make('title')
                                                        ->label('عنوان')
                                                        ->required()
                                                        ->maxLength(255),
                                                    static::iconsaxIconPicker('icon', 'آیکن'),
                                                    static::iconsaxIconSizeInput(),
                                                    Forms\Components\ViewField::make('image')
                                                        ->label('تصویر')
                                                        ->view('filament.forms.components.media-library-url-picker')
                                                        ->viewData(fn (): array => [
                                                            'images' => static::mediaLibraryImageItems(),
                                                        ])
                                                        ->helperText('تصویر اختیاری برای این ویژگی.'),
                                                    ...static::imageSettingsFields('image'),
                                                    Forms\Components\Textarea::make('description')
                                                        ->label('توضیحات')
                                                        ->rows(3)
                                                        ->columnSpanFull(),
                                                    Forms\Components\TextInput::make('button_label')
                                                        ->label('متن دکمه')
                                                        ->maxLength(255),
                                                    Forms\Components\TextInput::make('button_url')
                                                        ->label('لینک دکمه')
                                                        ->maxLength(255),
                                                ])
                                                ->columns(3)
                                                ->columnSpanFull()
                                                ->collapsible()
                                                ->collapsed()
                                                ->reorderable()
                                                ->visible(fn (Get $get): bool => ($get('items_mode') ?? 'static') === 'static'),
                                        ]),
                                    Forms\Components\Builder\Block::make('stats_section')
                                        ->label('بخش آمار')
                                        ->icon('heroicon-o-chart-bar')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->maxLength(255),
                                            static::headingTagField(),
                                            Forms\Components\Textarea::make('section_description')
                                                ->label('توضیحات بخش')
                                                ->rows(3)
                                                ->columnSpanFull(),
                                            Forms\Components\TextInput::make('inner_width_percent')
                                                ->label('عرض کانتینر داخلی')
                                                ->numeric()
                                                ->minValue(20)
                                                ->maxValue(100)
                                                ->default(70)
                                                ->suffix('%')
                                                ->helperText('چند درصد از عرض بخش تمام‌عرض را محتوای داخلی بگیرد.'),
                                            Forms\Components\Repeater::make('items')
                                                ->label('آمارها')
                                                ->cloneable()
                                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['value'] ?? 'آمار')
                                                ->schema([
                                                    Forms\Components\TextInput::make('value')
                                                        ->label('عدد / مقدار')
                                                        ->required()
                                                        ->maxLength(80)
                                                        ->helperText('مثلا 15، 2,000 یا +1870'),
                                                    Forms\Components\TextInput::make('label')
                                                        ->label('عنوان')
                                                        ->required()
                                                        ->maxLength(120)
                                                        ->helperText('مثلا سال سابقه، مشتری، پروژه ساختمانی'),
                                                    Forms\Components\TextInput::make('description')
                                                        ->label('توضیح کوتاه')
                                                        ->maxLength(180),
                                                ])
                                                ->columns(3)
                                                ->columnSpanFull()
                                                ->collapsible()
                                                ->reorderable(),
                                        ])
                                        ->columns(2),
                                    Forms\Components\Builder\Block::make('faq')
                                        ->label('پرسش‌های متداول')
                                        ->icon('heroicon-o-question-mark-circle')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->required()
                                                ->maxLength(255),
                                            static::headingTagField(),
                                            Forms\Components\Repeater::make('items')
                                                ->label('پرسش‌ها')
                                                ->cloneable()
                                                ->schema([
                                                    Forms\Components\TextInput::make('question')
                                                        ->label('پرسش')
                                                        ->required()
                                                        ->maxLength(255),
                                                    Forms\Components\Textarea::make('answer')
                                                        ->label('پاسخ')
                                                        ->required()
                                                        ->rows(3),
                                                ])
                                                ->columnSpanFull()
                                                ->reorderable(),
                                        ]),
                                    Forms\Components\Builder\Block::make('gallery')
                                        ->label('گالری')
                                        ->icon('heroicon-o-photo')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->required()
                                                ->maxLength(255),
                                            static::headingTagField(),
                                            Forms\Components\Repeater::make('images')
                                                ->label('تصاویر')
                                                ->cloneable()
                                                ->schema([
                                                    Forms\Components\ViewField::make('url')
                                                        ->label('تصویر')
                                                        ->view('filament.forms.components.media-library-url-picker')
                                                        ->viewData(fn (): array => [
                                                            'images' => static::mediaLibraryImageItems(),
                                                        ])
                                                        ->required()
                                                        ->helperText('از کتابخانه رسانه انتخاب کنید یا آدرس تصویر را وارد کنید.'),
                                                    ...static::imageSettingsFields('image'),
                                                    Forms\Components\TextInput::make('alt')
                                                        ->label('متن جایگزین')
                                                        ->maxLength(255),
                                                ])
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->reorderable(),
                                        ]),
                                    Forms\Components\Builder\Block::make('testimonials')
                                        ->label('نظرات مشتریان')
                                        ->icon('heroicon-o-chat-bubble-left-right')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->required()
                                                ->maxLength(255),
                                            static::headingTagField(),
                                            Forms\Components\Repeater::make('items')
                                                ->label('نظرات')
                                                ->cloneable()
                                                ->schema([
                                                    Forms\Components\TextInput::make('name')
                                                        ->label('نام')
                                                        ->required()
                                                        ->maxLength(255),
                                                    Forms\Components\TextInput::make('role')
                                                        ->label('سمت / شرکت')
                                                        ->maxLength(255),
                                                    Forms\Components\ViewField::make('avatar')
                                                        ->label('تصویر پروفایل')
                                                        ->view('filament.forms.components.media-library-url-picker')
                                                        ->viewData(fn (): array => [
                                                            'images' => static::mediaLibraryImageItems(),
                                                        ])
                                                        ->helperText('تصویر پروفایل اختیاری.'),
                                                    ...static::imageSettingsFields('avatar'),
                                                    Forms\Components\Textarea::make('quote')
                                                        ->label('متن نظر')
                                                        ->required()
                                                        ->rows(3)
                                                        ->columnSpanFull(),
                                                ])
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->reorderable(),
                                        ]),
                                    Forms\Components\Builder\Block::make('featured_projects')
                                        ->label('پروژه‌های ویژه')
                                        ->icon('heroicon-o-briefcase')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->required()
                                                ->maxLength(255)
                                                ->default('پروژه‌های ویژه'),
                                            static::headingTagField(),
                                            Forms\Components\Textarea::make('section_description')
                                                ->label('توضیحات بخش')
                                                ->rows(3)
                                                ->columnSpanFull(),
                                            Forms\Components\Select::make('source')
                                                ->label('منبع نمایش')
                                                ->required()
                                                ->options([
                                                    'featured' => 'پروژه‌های ویژه',
                                                    'latest' => 'آخرین پروژه‌ها',
                                                    'category' => 'پروژه‌های یک دسته',
                                                ])
                                                ->default('featured')
                                                ->live(),
                                            Forms\Components\Select::make('project_category_id')
                                                ->label('دسته پروژه')
                                                ->options(fn (): array => ProjectCategory::query()
                                                    ->active()
                                                    ->orderBy('sort_order')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->all())
                                                ->searchable()
                                                ->visible(fn (Get $get): bool => $get('source') === 'category'),
                                            Forms\Components\TextInput::make('limit')
                                                ->label('تعداد نمایش')
                                                ->required()
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(12)
                                                ->default(3),
                                            Forms\Components\TextInput::make('button_label')
                                                ->label('متن دکمه')
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('button_url')
                                                ->label('لینک دکمه')
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),
                                    Forms\Components\Builder\Block::make('featured_products')
                                        ->label('محصولات ویژه')
                                        ->icon('heroicon-o-shopping-bag')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->required()
                                                ->maxLength(255)
                                                ->default('محصولات ویژه'),
                                            static::headingTagField(),
                                            Forms\Components\Textarea::make('section_description')
                                                ->label('توضیحات بخش')
                                                ->rows(3)
                                                ->columnSpanFull(),
                                            Forms\Components\Select::make('source')
                                                ->label('منبع نمایش')
                                                ->required()
                                                ->options([
                                                    'featured' => 'محصولات ویژه',
                                                    'latest' => 'آخرین محصولات',
                                                    'category' => 'محصولات یک دسته',
                                                ])
                                                ->default('featured')
                                                ->live(),
                                            Forms\Components\Select::make('product_category_id')
                                                ->label('دسته محصول')
                                                ->options(fn (): array => ProductCategory::query()
                                                    ->active()
                                                    ->orderBy('sort_order')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->all())
                                                ->searchable()
                                                ->visible(fn (Get $get): bool => $get('source') === 'category'),
                                            Forms\Components\TextInput::make('limit')
                                                ->label('تعداد نمایش')
                                                ->required()
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(12)
                                                ->default(3),
                                            Forms\Components\TextInput::make('button_label')
                                                ->label('متن دکمه')
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('button_url')
                                                ->label('لینک دکمه')
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),
                                    Forms\Components\Builder\Block::make('featured_galleries')
                                        ->label('گالری‌های ویژه')
                                        ->icon('heroicon-o-photo')
                                        ->schema([
                                            ...static::blockStyleFields(eyebrow: true),
                                            Forms\Components\TextInput::make('section_title')
                                                ->label('عنوان بخش')
                                                ->required()
                                                ->maxLength(255)
                                                ->default('گالری‌های ویژه'),
                                            static::headingTagField(),
                                            Forms\Components\Textarea::make('section_description')
                                                ->label('توضیحات بخش')
                                                ->rows(3)
                                                ->columnSpanFull(),
                                            Forms\Components\Select::make('source')
                                                ->label('منبع نمایش')
                                                ->required()
                                                ->options([
                                                    'featured' => 'گالری‌های ویژه',
                                                    'latest' => 'آخرین گالری‌ها',
                                                    'category' => 'گالری‌های یک دسته',
                                                    'project' => 'گالری‌های یک پروژه',
                                                ])
                                                ->default('featured')
                                                ->live(),
                                            Forms\Components\Select::make('gallery_category_id')
                                                ->label('دسته گالری')
                                                ->options(fn (): array => GalleryCategory::query()
                                                    ->active()
                                                    ->orderBy('sort_order')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->all())
                                                ->searchable()
                                                ->visible(fn (Get $get): bool => $get('source') === 'category'),
                                            Forms\Components\Select::make('project_id')
                                                ->label('پروژه')
                                                ->options(fn (): array => Project::query()
                                                    ->published()
                                                    ->orderBy('title')
                                                    ->pluck('title', 'id')
                                                    ->all())
                                                ->searchable()
                                                ->visible(fn (Get $get): bool => $get('source') === 'project'),
                                            Forms\Components\Select::make('type')
                                                ->label('فیلتر نوع')
                                                ->options([
                                                    'all' => 'همه',
                                                    'image' => 'تصویر',
                                                    'video' => 'ویدیو',
                                                    'mixed' => 'ترکیبی',
                                                ])
                                                ->default('all'),
                                            Forms\Components\TextInput::make('limit')
                                                ->label('تعداد نمایش')
                                                ->required()
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(12)
                                                ->default(3),
                                            Forms\Components\TextInput::make('button_label')
                                                ->label('متن دکمه')
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('button_url')
                                                ->label('لینک دکمه')
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),
                                    Forms\Components\Builder\Block::make('custom_html')
                                        ->label('کد سفارشی')
                                        ->icon('heroicon-o-code-bracket-square')
                                        ->schema([
                                            Forms\Components\Textarea::make('code')
                                                ->label('کد')
                                                ->rows(18)
                                                ->required()
                                                ->helperText('فقط برای مدیران مورد اعتماد. این کد بدون فیلتر رندر می‌شود و می‌تواند شامل نشانه‌گذاری، استایل و اسکریپت باشد.')
                                                ->columnSpanFull(),
                                        ]),
                                ])
                                ->collapsible()
                                ->reorderable()
                                ->columnSpanFull()
                                ->helperText('بلوک‌ها اختیاری هستند. اگر بلوکی اضافه نشود، برگه از متن محتوای ویرایشگر استفاده می‌کند.'),
                        ]),
                    Forms\Components\Tabs\Tab::make('تصویر شاخص')
                        ->schema([
                            Forms\Components\ViewField::make('featured_media_id')
                                ->label('تصویر شاخص')
                                ->view('filament.forms.components.media-library-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Page $record): void {
                                    $set(
                                        'featured_media_id',
                                        $record?->featuredImage()?->getCustomProperty('source_media_id')
                                            ?: ($record?->featuredImage() ? '__keep_existing__' : null),
                                    );
                                })
                                ->helperText('یک تصویر موجود از کتابخانه رسانه انتخاب کنید. برای تصویر جدید ابتدا از بخش رسانه، تصویر را بارگذاری کنید.'),
                        ]),
                    Forms\Components\Tabs\Tab::make('سئو')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('عنوان سئو')
                                ->maxLength(70)
                                ->helperText('پیشنهاد: حداکثر ۷۰ کاراکتر. اگر خالی باشد از عنوان برگه استفاده می‌شود.'),
                            Forms\Components\Textarea::make('seo_description')
                                ->label('توضیحات سئو')
                                ->maxLength(160)
                                ->helperText('پیشنهاد: حداکثر ۱۶۰ کاراکتر. اگر خالی باشد از محتوای برگه یا تنظیمات پیش‌فرض سایت استفاده می‌شود.')
                                ->rows(3)
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('seo_image')
                                ->label('تصویر شبکه‌های اجتماعی')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->helperText('اگر خالی باشد از تصویر شاخص استفاده می‌شود.')
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('robots_index')
                                ->label('اجازه ایندکس این برگه توسط موتورهای جستجو')
                                ->default(true),
                            Forms\Components\Toggle::make('robots_follow')
                                ->label('اجازه دنبال کردن لینک‌ها توسط موتورهای جستجو')
                                ->default(true),
                            Forms\Components\TextInput::make('seo_keywords')
                                ->label('کلمات کلیدی متا')
                                ->maxLength(255)
                                ->helperText('فیلد اختیاری کلمات کلیدی متا برای سازگاری با ساختارهای قدیمی.'),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('انتشار')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label('وضعیت')
                                ->required()
                                ->options([
                                    'draft' => 'پیش‌نویس',
                                    'published' => 'منتشرشده',
                                    'archived' => 'بایگانی‌شده',
                                ])
                                ->default('draft'),
                            Forms\Components\DateTimePicker::make('published_at')
                                ->label('زمان انتشار')
                                ->seconds(false)
                                ->helperText('اگر وضعیت منتشرشده باشد و این فیلد خالی بماند، برگه بلافاصله منتشر می‌شود. دکمه مشاهده عمومی فقط برای رکوردهای منتشرشده نمایش داده می‌شود.'),
                        ])
                        ->columns(2),
                ])
                ->columnSpanFull(),
        ]), function () use ($startedAt): void {
            static::logPageEditPerf('form build ms', [
                'ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            ]);
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('featured_image')
                    ->collection('featured_image')
                    ->conversion('thumb')
                    ->label('تصویر'),
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('نامک')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('template')
                    ->label('قالب')
                    ->formatStateUsing(fn (string $state): string => [
                        'default' => 'پیش‌فرض',
                        'home' => 'صفحه اصلی',
                        'landing' => 'لندینگ',
                    ][$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->formatStateUsing(fn (string $state): string => [
                        'draft' => 'پیش‌نویس',
                        'published' => 'منتشرشده',
                        'archived' => 'بایگانی‌شده',
                    ][$state] ?? $state)
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('زمان انتشار')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین ویرایش')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'draft' => 'پیش‌نویس',
                        'published' => 'منتشرشده',
                        'archived' => 'بایگانی‌شده',
                    ]),
                Tables\Filters\SelectFilter::make('template')
                    ->label('قالب')
                    ->options([
                        'default' => 'پیش‌فرض',
                        'home' => 'صفحه اصلی',
                        'landing' => 'لندینگ',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('پیش‌نمایش')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Page $record): string => route('admin.preview.pages.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('viewPublic')
                    ->label('مشاهده')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $record): string => static::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Page $record): bool => static::isPubliclyVisible($record)),
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->modalHeading('حذف برگه')
                    ->modalSubmitActionLabel('حذف')
                    ->modalCancelActionLabel('انصراف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف گروهی')
                        ->modalHeading('حذف برگه‌های انتخاب‌شده')
                        ->modalSubmitActionLabel('حذف')
                        ->modalCancelActionLabel('انصراف'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function publicUrl(Page $page): string
    {
        return match ($page->slug) {
            'home' => route('home'),
            'contact' => route('contact.create'),
            default => route('pages.show', $page->slug),
        };
    }

    public static function isPubliclyVisible(Page $page): bool
    {
        return $page->status === 'published'
            && (blank($page->published_at) || $page->published_at->lte(now()));
    }

    protected static function blockStyleFields(bool $eyebrow = false, ?string $visibleForTemplate = null): array
    {
        return [
            Forms\Components\Select::make('section_background')
                ->label('پس‌زمینه بخش')
                ->options([
                    'default' => 'پیش‌فرض',
                    'muted' => 'ملایم',
                    'dark' => 'تیره',
                ])
                ->default('default')
                ->visible(fn (Get $get): bool => $visibleForTemplate === null || blank($get('template')) || $get('template') === $visibleForTemplate),
            Forms\Components\Select::make('alignment')
                ->label('چیدمان')
                ->options([
                    'left' => 'چپ',
                    'center' => 'وسط',
                ])
                ->default('center')
                ->visible(fn (Get $get): bool => $visibleForTemplate === null || blank($get('template')) || $get('template') === $visibleForTemplate),
            ...($eyebrow ? [
                Forms\Components\TextInput::make('eyebrow')
                    ->label('برچسب بالای عنوان')
                    ->maxLength(255)
                    ->helperText('یک برچسب کوتاه اختیاری بالای عنوان بخش.'),
            ] : []),
        ];
    }

    protected static function imageSettingsFields(string $prefix = 'image', string $label = 'تنظیمات تصویر', ?\Closure $visible = null): array
    {
        $section = Forms\Components\Section::make($label)
            ->schema([
                Forms\Components\Grid::make([
                    'default' => 1,
                    'xl' => 2,
                ])
                    ->schema([
                        Forms\Components\Section::make('دسکتاپ')
                            ->schema(static::imageDeviceSettingsFields($prefix))
                            ->columns(6),
                        Forms\Components\Section::make('موبایل')
                            ->schema(static::imageDeviceSettingsFields($prefix, 'mobile'))
                            ->columns(6),
                    ]),
            ])
            ->collapsible()
            ->collapsed()
            ->columnSpanFull();

        if ($visible) {
            $section->visible($visible);
        }

        return [
            $section,
        ];
    }

    protected static function imageDeviceSettingsFields(string $prefix, ?string $device = null): array
    {
        $keyPrefix = $device === 'mobile' ? "{$prefix}_mobile" : $prefix;

        return [
            Forms\Components\TextInput::make("{$keyPrefix}_width_value")
                ->label('عرض')
                ->numeric()
                ->minValue(0)
                ->placeholder('مثلا 100')
                ->columnSpan(2),
            Forms\Components\Select::make("{$keyPrefix}_width_unit")
                ->label('واحد عرض')
                ->options(static::imageSizeUnitOptions())
                ->default('%')
                ->columnSpan(1),
            Forms\Components\TextInput::make("{$keyPrefix}_height_value")
                ->label('ارتفاع')
                ->numeric()
                ->minValue(0)
                ->placeholder('مثلا 240')
                ->columnSpan(2),
            Forms\Components\Select::make("{$keyPrefix}_height_unit")
                ->label('واحد ارتفاع')
                ->options(static::imageSizeUnitOptions())
                ->default('px')
                ->columnSpan(1),
            Forms\Components\Select::make("{$keyPrefix}_fit")
                ->label('واکنش تصویر')
                ->options([
                    'normal' => 'عادی',
                    'cover' => 'پوشش',
                    'contain' => 'کامل دیده شود',
                ])
                ->default('normal')
                ->helperText('عادی یعنی اندازه تصویر تغییر اجباری نداشته باشد. پوشش یعنی تصویر کل قاب را پر کند.')
                ->columnSpanFull(),
        ];
    }

    protected static function imageSizeUnitOptions(): array
    {
        return [
            '%' => 'درصد',
            'px' => 'پیکسل',
        ];
    }

    protected static function headingTagField(string $label = 'تگ عنوان'): Forms\Components\Select
    {
        return Forms\Components\Select::make('heading_tag')
            ->label($label)
            ->options([
                'h1' => 'H1',
                'h2' => 'H2',
            ])
            ->default('h2')
            ->native(false);
    }

    protected static function ctaFields(): array
    {
        return [
            Forms\Components\Select::make('cta_template')
                ->label('قالب دعوت به اقدام')
                ->options([
                    'classic' => 'قالب ساده',
                    'image' => 'قالب تصویری',
                ])
                ->default('classic')
                ->live()
                ->required(),
            Forms\Components\TextInput::make('content_width')
                ->label('عرض بخش متن')
                ->numeric()
                ->minValue(240)
                ->maxValue(1400)
                ->default(580)
                ->suffix('px')
                ->helperText('عرض محتوای متنی در قالب تصویری.')
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image'),
            Forms\Components\Select::make('section_background')
                ->label('پس‌زمینه بخش')
                ->options([
                    'default' => 'پیش‌فرض',
                    'muted' => 'ملایم',
                    'dark' => 'تیره',
                ])
                ->default('default')
                ->visible(fn (Get $get): bool => ($get('cta_template') ?? 'classic') === 'classic'),
            Forms\Components\Select::make('alignment')
                ->label('چیدمان')
                ->options([
                    'left' => 'چپ',
                    'center' => 'وسط',
                ])
                ->default('center')
                ->visible(fn (Get $get): bool => ($get('cta_template') ?? 'classic') === 'classic'),
            Forms\Components\TextInput::make('eyebrow')
                ->label('برچسب بالای عنوان')
                ->maxLength(255)
                ->helperText('یک برچسب کوتاه اختیاری بالای عنوان بخش.')
                ->visible(fn (Get $get): bool => ($get('cta_template') ?? 'classic') === 'classic'),
            Forms\Components\ViewField::make('background_image')
                ->label('تصویر پس‌زمینه')
                ->view('filament.forms.components.media-library-url-picker')
                ->viewData(fn (): array => [
                    'images' => static::mediaLibraryImageItems(),
                ])
                ->helperText('فقط برای قالب تصویری دعوت به اقدام.')
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image')
                ->columnSpanFull(),
            ...static::imageSettingsFields(
                'background_image',
                'تنظیمات تصویر پس‌زمینه',
                fn (Get $get): bool => $get('cta_template') === 'image',
            ),
            Forms\Components\TextInput::make('title')
                ->label('عنوان')
                ->required()
                ->maxLength(255),
            static::headingTagField(),
            Forms\Components\Textarea::make('description')
                ->label('توضیحات')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('button_label')
                ->label('متن دکمه اصلی')
                ->maxLength(255),
            Forms\Components\TextInput::make('button_url')
                ->label('لینک دکمه اصلی')
                ->maxLength(255),
            Forms\Components\TextInput::make('secondary_button_label')
                ->label('متن دکمه دوم')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image'),
            Forms\Components\TextInput::make('secondary_button_url')
                ->label('لینک دکمه دوم')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('cta_template') === 'image'),
        ];
    }

    protected static function logPageEditPerf(string $label, array $context = []): void
    {
        if (! request()->routeIs('filament.admin.resources.pages.edit')) {
            return;
        }

        Log::info("PERF PageResource edit: {$label}", $context);
    }
}
