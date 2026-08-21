<?php

namespace App\Filament\Resources;

use App\Enums\ServicePricingMode;
use App\Enums\ServiceUnit;
use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use App\Services\ServiceSettings;
use App\Services\SettingsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ServiceResource extends Resource
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = Service::class;

    protected static ?string $navigationGroup = 'محتوا';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('ویرایشگر خدمت')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('اطلاعات اصلی')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('نام خدمت')
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
                                ->unique(ignoreRecord: true),
                            Forms\Components\Textarea::make('excerpt')
                                ->label('خلاصه کوتاه')
                                ->rows(3)
                                ->helperText('خلاصه‌ای کوتاه برای کارت خدمت و استفاده به‌عنوان متن جایگزین سئو.')
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('overview')
                                ->label('معرفی کامل')
                                ->columnSpanFull(),
                            static::iconsaxIconPicker(
                                'icon',
                                'آیکن',
                                'کلاس آیکن در همان فیلد متنی قبلی ذخیره می‌شود؛ فایل رسانه‌ای ایجاد نمی‌شود.',
                            ),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('ترتیب نمایش')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('مزایا')
                        ->hidden(fn (): bool => ! static::sectionEnabled('benefits'))
                        ->schema([
                            Forms\Components\Repeater::make('benefits')
                                ->label('مزایای خدمت')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('عنوان')
                                        ->required()
                                        ->maxLength(255),
                                    static::iconsaxIconPicker('icon', 'آیکن'),
                                    Forms\Components\Textarea::make('description')
                                        ->label('توضیحات')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'مزیت')
                                ->reorderable()
                                ->cloneable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('فرآیند اجرا')
                        ->hidden(fn (): bool => ! static::sectionEnabled('process'))
                        ->schema([
                            Forms\Components\Repeater::make('process')
                                ->label('مراحل اجرا')
                                ->schema([
                                    Forms\Components\TextInput::make('step')
                                        ->label('شماره مرحله')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->helperText('این شماره هنگام ذخیره براساس ترتیب مراحل تولید می‌شود.'),
                                    Forms\Components\TextInput::make('title')
                                        ->label('عنوان')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('description')
                                        ->label('توضیحات')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                ->itemLabel(fn (array $state): string => filled($state['step'] ?? null)
                                    ? 'مرحله '.$state['step'].': '.($state['title'] ?? 'بدون عنوان')
                                    : ($state['title'] ?? 'مرحله جدید'))
                                ->reorderable()
                                ->cloneable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('اقلام تحویلی')
                        ->hidden(fn (): bool => ! static::sectionEnabled('deliverables'))
                        ->schema([
                            Forms\Components\Repeater::make('deliverables')
                                ->label('اقلام تحویلی')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('عنوان')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('description')
                                        ->label('توضیحات')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'قلم تحویلی')
                                ->reorderable()
                                ->cloneable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('رسانه')
                        ->hidden(fn (): bool => ! static::sectionEnabled('media'))
                        ->schema([
                            Forms\Components\ViewField::make('featured_media_id')
                                ->label('تصویر شاخص')
                                ->view('filament.forms.components.media-library-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Service $record): void {
                                    $set(
                                        'featured_media_id',
                                        $record?->featuredImage()?->getCustomProperty('source_media_id')
                                            ?: ($record?->featuredImage() ? '__keep_existing__' : null),
                                    );
                                })
                                ->helperText('یک تصویر موجود را از کتابخانه رسانه انتخاب کنید.'),
                            Forms\Components\ViewField::make('gallery_media_ids')
                                ->label('گالری خدمت')
                                ->view('filament.forms.components.media-library-multiple-picker')
                                ->viewData(fn (?Service $record): array => [
                                    'images' => $record
                                        ? static::mediaLibraryImageItemsWithCollection($record, 'gallery')
                                        : static::mediaLibraryImageItems(),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Service $record): void {
                                    $set('gallery_media_ids', $record
                                        ? static::mediaLibraryCollectionState($record, 'gallery')
                                        : []);
                                })
                                ->helperText('تصاویر گالری را از کتابخانه رسانه انتخاب و مرتب کنید.')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('پروژه‌های مرتبط')
                        ->hidden(fn (): bool => ! static::sectionEnabled('related_projects'))
                        ->schema([
                            Forms\Components\Select::make('projects')
                                ->label('پروژه‌ها')
                                ->relationship('projects', 'title')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->optionsLimit(50)
                                ->helperText('فقط Relation ساختاریافته مدیریت می‌شود؛ خدمات قدیمی JSON پروژه تغییری نمی‌کنند.')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('قیمت و ارائه خدمت')
                        ->schema([
                            Forms\Components\Toggle::make('available_for_activities')
                                ->label('قابل استفاده در فعالیت‌های مشتریان')
                                ->default(false)
                                ->live(),
                            Forms\Components\Placeholder::make('activity_catalog_note')
                                ->label('کاتالوگ فعالیت')
                                ->content(fn (): string => static::serviceSettings()->activityCatalogEnabled()
                                    ? 'این خدمت در صورت فعال بودن گزینه بالا، بدون وابستگی به وضعیت انتشار عمومی قابل انتخاب است.'
                                    : 'اتصال کاتالوگ خدمات به فعالیت‌ها اکنون در تنظیمات سایت غیرفعال است.'),
                            Forms\Components\Group::make([
                                Forms\Components\Select::make('pricing_mode')
                                    ->label('روش قیمت‌گذاری')
                                    ->options(ServicePricingMode::options())
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        if ($state === ServicePricingMode::Hourly->value) {
                                            $set('unit', ServiceUnit::Hour->value);
                                            $set('custom_unit_label', null);
                                        } elseif ($state === ServicePricingMode::Fixed->value) {
                                            $set('unit', ServiceUnit::Fixed->value);
                                            $set('custom_unit_label', null);
                                        } elseif ($state === ServicePricingMode::PerUnit->value) {
                                            $set('unit', null);
                                            $set('custom_unit_label', null);
                                        }
                                    }),
                                Forms\Components\Select::make('unit')
                                    ->label('واحد')
                                    ->options(fn (Get $get): array => static::unitOptionsForMode($get('pricing_mode')))
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        if ($state !== ServiceUnit::Custom->value) {
                                            $set('custom_unit_label', null);
                                        }
                                    })
                                    ->required(fn (Get $get): bool => filled($get('pricing_mode'))),
                                Forms\Components\TextInput::make('custom_unit_label')
                                    ->label('عنوان واحد سفارشی')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('unit') === ServiceUnit::Custom->value)
                                    ->required(fn (Get $get): bool => $get('unit') === ServiceUnit::Custom->value),
                                Forms\Components\TextInput::make('default_unit_price')
                                    ->label('قیمت پایه واحد')
                                    ->numeric()
                                    ->minValue(0)
                                    ->inputMode('decimal'),
                                Forms\Components\TextInput::make('currency_code')
                                    ->label('ارز')
                                    ->placeholder(fn (): string => (string) app(SettingsService::class)->get('default_service_currency', 'IRT'))
                                    ->length(3)
                                    ->rules(['nullable', 'regex:/^[A-Z]{3}$/']),
                            ])
                                ->hidden(fn (): bool => ! static::serviceSettings()->pricingEnabled())
                                ->columns(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('سئو')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('عنوان سئو')
                                ->maxLength(70)
                                ->helperText('حداکثر ۷۰ نویسه پیشنهاد می‌شود؛ در صورت خالی بودن نام خدمت قابل استفاده است.'),
                            Forms\Components\Textarea::make('seo_description')
                                ->label('توضیحات سئو')
                                ->maxLength(160)
                                ->helperText('حداکثر ۱۶۰ نویسه پیشنهاد می‌شود؛ در صورت خالی بودن خلاصه خدمت قابل استفاده است.')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('انتشار')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label('وضعیت')
                                ->required()
                                ->options(static::statusOptions())
                                ->default(Service::STATUS_DRAFT),
                            Forms\Components\DateTimePicker::make('published_at')->jalali()
                                ->label('زمان انتشار')
                                ->seconds(false)
                                ->disabled(fn (Get $get): bool => $get('status') !== Service::STATUS_PUBLISHED)
                                ->helperText('برای انتشار فوری خالی بگذارید؛ تاریخ آینده انتشار زمان‌بندی‌شده ایجاد می‌کند.'),
                        ])
                        ->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('featured_image')
                    ->collection('featured_image')
                    ->conversion('thumb')
                    ->label('تصویر'),
                Tables\Columns\TextColumn::make('name')
                    ->label('نام خدمت')
                    ->searchable(['name', 'slug'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->formatStateUsing(fn (string $state): string => static::statusOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Service::STATUS_PUBLISHED, Service::STATUS_ACTIVE => 'success',
                        Service::STATUS_DRAFT => 'gray',
                        Service::STATUS_ARCHIVED => 'warning',
                        Service::STATUS_INACTIVE => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('projects_count')
                    ->label('پروژه‌ها')
                    ->counts('projects')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('زمان انتشار')
                    ->jalaliDateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین ویرایش')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(static::statusOptions()),
                Tables\Filters\SelectFilter::make('publication_state')
                    ->label('وضعیت انتشار')
                    ->options([
                        'public' => 'قابل نمایش',
                        'scheduled' => 'زمان‌بندی‌شده',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'public' => $query->published(),
                            'scheduled' => $query
                                ->where('status', Service::STATUS_PUBLISHED)
                                ->where('published_at', '>', now()),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->modalHeading('حذف خدمت')
                    ->modalDescription('آیا از حذف این خدمت اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                    ->modalSubmitActionLabel('بله، حذف شود')
                    ->successNotificationTitle('خدمت حذف شد.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف خدمات انتخاب‌شده')
                        ->modalHeading('حذف خدمات انتخاب‌شده')
                        ->modalDescription('آیا از حذف خدمات انتخاب‌شده اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                        ->modalSubmitActionLabel('بله، حذف شوند')
                        ->successNotificationTitle('خدمات انتخاب‌شده حذف شدند.'),
                ]),
            ])
            ->emptyStateHeading('هنوز خدمتی ثبت نشده است')
            ->emptyStateDescription('برای معرفی خدمات کسب‌وکار، نخستین خدمت را ایجاد کنید.')
            ->emptyStateIcon('heroicon-o-wrench-screwdriver');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            Service::STATUS_DRAFT => 'پیش‌نویس',
            Service::STATUS_PUBLISHED => 'منتشرشده',
            Service::STATUS_ARCHIVED => 'بایگانی‌شده',
            Service::STATUS_ACTIVE => 'فعال قدیمی',
            Service::STATUS_INACTIVE => 'غیرفعال قدیمی',
        ];
    }

    public static function sectionEnabled(string $section): bool
    {
        return static::serviceSettings()->formSectionEnabled($section);
    }

    private static function serviceSettings(): ServiceSettings
    {
        return app(ServiceSettings::class);
    }

    private static function unitOptionsForMode(?string $mode): array
    {
        $options = static::serviceSettings()->allowedUnitOptions();

        return match ($mode) {
            ServicePricingMode::Hourly->value => array_intersect_key($options, [ServiceUnit::Hour->value => true]),
            ServicePricingMode::Fixed->value => array_intersect_key($options, [ServiceUnit::Fixed->value => true]),
            ServicePricingMode::PerUnit->value => array_diff_key($options, [
                ServiceUnit::Hour->value => true,
                ServiceUnit::Fixed->value => true,
            ]),
            default => $options,
        };
    }
}
