<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use App\Models\ProjectDiscoveryVocabulary;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProjectResource extends Resource
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = Project::class;

    protected static ?string $navigationGroup = 'پروژه‌ها';

    protected static ?string $navigationLabel = 'پروژه‌ها';

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->projectsEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('ویرایشگر پروژه')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('محتوا')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('عنوان پروژه')
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
                            Forms\Components\Select::make('project_category_id')
                                ->label('دسته‌بندی پروژه')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Textarea::make('excerpt')
                                ->label('خلاصه پروژه')
                                ->rows(3)
                                ->helperText('خلاصه‌ای کوتاه برای نمایش در کارت پروژه و استفاده به‌عنوان متن جایگزین سئو.')
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('content')
                                ->label('محتوای پروژه')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('جزئیات تکمیلی')
                        ->schema([
                            Forms\Components\DatePicker::make('project_date')->jalali()
                                ->label('تاریخ پروژه'),
                            Forms\Components\TextInput::make('external_url')
                                ->label('نشانی وب پروژه')
                                ->url()
                                ->maxLength(255),
                            Forms\Components\Repeater::make('services')
                                ->label('خدمات قدیمی')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('عنوان خدمت')
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->helperText('این اطلاعات قدیمی همچنان پشتیبانی می‌شود. برای اتصال خدمات ساختاریافته از زبانه «مطالعه موردی» استفاده کنید.')
                                ->columnSpanFull()
                                ->reorderable(),
                            Forms\Components\Repeater::make('attributes')
                                ->label('ویژگی‌های تکمیلی')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('عنوان ویژگی')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('value')
                                        ->label('مقدار')
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->reorderable(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('مطالعه موردی')
                        ->schema([
                            Forms\Components\TextInput::make('client_name')
                                ->label('کارفرما')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('location')
                                ->label('موقعیت پروژه')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('industry')
                                ->label('حوزه فعالیت')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('project_type')
                                ->label('نوع پروژه')
                                ->maxLength(255),
                            Forms\Components\Select::make('relatedServices')
                                ->label('خدمات مرتبط')
                                ->relationship(
                                    name: 'relatedServices',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query, ?Project $record): Builder => static::serviceOptionsQuery($query, $record),
                                )
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->optionsLimit(50)
                                ->helperText('خدمات ساختاریافته مرتبط با این پروژه را انتخاب کنید. خدمات قدیمی در زبانه «جزئیات تکمیلی» همچنان در دسترس هستند.')
                                ->columnSpanFull(),
                            Forms\Components\DatePicker::make('project_started_at')->jalali()
                                ->label('تاریخ شروع پروژه'),
                            Forms\Components\DatePicker::make('project_completed_at')->jalali()
                                ->label('تاریخ پایان پروژه'),
                            Forms\Components\Textarea::make('challenge')
                                ->label('چالش پروژه')
                                ->rows(6)
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('solution')
                                ->label('راهکار اجراشده')
                                ->rows(6)
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('results_summary')
                                ->label('خلاصه نتایج')
                                ->rows(6)
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('client_quote')
                                ->label('نظر کارفرما')
                                ->rows(4)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('فیلترهای گالری')
                        ->schema([
                            Forms\Components\Select::make('discoveryTerms')
                                ->label('ویژگی‌های قابل فیلتر')
                                ->relationship('discoveryTerms', 'name')
                                ->options(fn (): array => ProjectDiscoveryVocabulary::query()
                                    ->active()
                                    ->with(['terms' => fn ($query) => $query->active()])
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn ($vocabulary): array => [
                                        $vocabulary->name => $vocabulary->terms->pluck('name', 'id')->all(),
                                    ])
                                    ->all())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->helperText('گزینه‌ها را از بخش «فیلترهای گالری» تعریف کنید. هر پروژه می‌تواند چند گزینه از گروه‌های مختلف داشته باشد.')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('شاخص‌ها و دستاوردها')
                        ->schema([
                            Forms\Components\Repeater::make('metrics')
                                ->label('شاخص‌های پروژه')
                                ->relationship('metrics')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('عنوان شاخص')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('value')
                                        ->label('مقدار')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('prefix')
                                        ->label('پیشوند')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('suffix')
                                        ->label('پسوند')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('description')
                                        ->label('توضیحات')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    static::iconsaxIconPicker('icon', 'آیکن'),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('ترتیب نمایش')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->helperText('با جابه‌جایی شاخص‌ها، این مقدار به‌صورت خودکار به‌روزرسانی می‌شود.'),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'شاخص')
                                ->orderColumn('sort_order')
                                ->reorderable()
                                ->cloneable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('تصاویر')
                        ->schema([
                            Forms\Components\ViewField::make('featured_media_id')
                                ->label('تصویر شاخص')
                                ->view('filament.forms.components.media-library-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Project $record): void {
                                    $set(
                                        'featured_media_id',
                                        $record?->featuredImage()?->getCustomProperty('source_media_id')
                                            ?: ($record?->featuredImage() ? '__keep_existing__' : null),
                                    );
                                })
                                ->helperText('یک تصویر از کتابخانه رسانه انتخاب کنید. برای افزودن تصویر جدید، ابتدا از بخش «رسانه ← بارگذاری رسانه» استفاده کنید.'),
                            Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                                ->collection('gallery')
                                ->label('گالری تصاویر پروژه')
                                ->multiple()
                                ->image()
                                ->imageEditor()
                                ->reorderable()
                                ->helperText('تصاویر گالری صفحه جزئیات پروژه را بارگذاری و مرتب کنید.')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('ویدئوها')
                        ->schema([
                            Forms\Components\Repeater::make('videos')
                                ->label('ویدئوهای پروژه')
                                ->relationship('videos')
                                ->schema([
                                    Forms\Components\TextInput::make('url')
                                        ->label('نشانی ویدئو')
                                        ->url()
                                        ->startsWith(['http://', 'https://'])
                                        ->required()
                                        ->maxLength(2048)
                                        ->helperText('YouTube، Vimeo و Aparat به‌صورت امن درون صفحه نمایش داده می‌شوند؛ سایر نشانی‌های معتبر به‌صورت پیوند خارجی باز می‌شوند.'),
                                    Forms\Components\TextInput::make('title')
                                        ->label('عنوان')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('caption')
                                        ->label('توضیح')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\ViewField::make('thumbnail_url')
                                        ->label('تصویر بندانگشتی')
                                        ->view('filament.forms.components.media-library-url-picker')
                                        ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                                        ->helperText('اختیاری؛ یک تصویر از کتابخانه رسانه برای پیش‌نمایش ویدئو انتخاب کنید.')
                                        ->columnSpanFull(),
                                    Forms\Components\Hidden::make('sort_order'),
                                ])
                                ->orderColumn('sort_order')
                                ->reorderable()
                                ->cloneable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->itemLabel(fn (array $state): string => $state['title'] ?? $state['url'] ?? 'ویدئوی پروژه')
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('سئو')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('عنوان سئو')
                                ->maxLength(70)
                                ->helperText('حداکثر ۷۰ نویسه پیشنهاد می‌شود. در صورت خالی بودن، عنوان پروژه استفاده خواهد شد.'),
                            Forms\Components\Textarea::make('seo_description')
                                ->label('توضیحات سئو')
                                ->maxLength(160)
                                ->helperText('حداکثر ۱۶۰ نویسه پیشنهاد می‌شود. در صورت خالی بودن، خلاصه پروژه، محتوا یا تنظیمات پیش‌فرض سایت استفاده خواهد شد.')
                                ->rows(3)
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('seo_image')
                                ->label('تصویر اشتراک‌گذاری')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->helperText('در صورت انتخاب نکردن تصویر، تصویر شاخص پروژه استفاده خواهد شد.')
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('robots_index')
                                ->label('اجازه فهرست‌شدن پروژه در موتورهای جست‌وجو')
                                ->default(true),
                            Forms\Components\Toggle::make('robots_follow')
                                ->label('اجازه دنبال‌کردن پیوندها توسط موتورهای جست‌وجو')
                                ->default(true),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('انتشار')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label('وضعیت انتشار')
                                ->required()
                                ->options([
                                    'draft' => 'پیش‌نویس',
                                    'published' => 'منتشرشده',
                                    'archived' => 'بایگانی‌شده',
                                ])
                                ->default('draft'),
                            Forms\Components\DateTimePicker::make('published_at')->jalali()
                                ->label('زمان انتشار')
                                ->seconds(false)
                                ->helperText('برای انتشار فوری پس از انتخاب وضعیت «منتشرشده»، این فیلد را خالی بگذارید. امکان مشاهده در سایت فقط برای پروژه‌های منتشرشده نمایش داده می‌شود.'),
                            Forms\Components\Toggle::make('is_featured')
                                ->label('پروژه شاخص')
                                ->default(false),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('ترتیب نمایش')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
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
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان پروژه')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('دسته‌بندی')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'پیش‌نویس',
                        'published' => 'منتشرشده',
                        'archived' => 'بایگانی‌شده',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('شاخص')
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('زمان انتشار')
                    ->jalaliDateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین ویرایش')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت انتشار')
                    ->options([
                        'draft' => 'پیش‌نویس',
                        'published' => 'منتشرشده',
                        'archived' => 'بایگانی‌شده',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->label('دسته‌بندی پروژه')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('پروژه شاخص')
                    ->placeholder('همه پروژه‌ها')
                    ->trueLabel('فقط پروژه‌های شاخص')
                    ->falseLabel('فقط پروژه‌های عادی'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('پیش‌نمایش')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Project $record): string => route('admin.preview.projects.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('viewPublic')
                    ->label('مشاهده در سایت')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Project $record): string => static::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Project $record): bool => static::isPubliclyVisible($record)),
                Tables\Actions\EditAction::make()
                    ->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->modalHeading('حذف پروژه')
                    ->modalDescription('آیا از حذف این پروژه اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                    ->modalSubmitActionLabel('بله، حذف شود')
                    ->successNotificationTitle('پروژه حذف شد.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف پروژه‌های انتخاب‌شده')
                        ->modalHeading('حذف پروژه‌های انتخاب‌شده')
                        ->modalDescription('آیا از حذف پروژه‌های انتخاب‌شده اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                        ->modalSubmitActionLabel('بله، حذف شوند')
                        ->successNotificationTitle('پروژه‌های انتخاب‌شده حذف شدند.'),
                ]),
            ])
            ->emptyStateHeading('هنوز پروژه‌ای ثبت نشده است')
            ->emptyStateDescription('برای نمایش نمونه‌کارها و مطالعه‌های موردی، نخستین پروژه را ایجاد کنید.')
            ->emptyStateIcon('heroicon-o-briefcase');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }

    public static function publicUrl(Project $project): string
    {
        return route('projects.show', $project->slug);
    }

    public static function isPubliclyVisible(Project $project): bool
    {
        return $project->status === 'published'
            && (blank($project->published_at) || $project->published_at->lte(now()));
    }

    public static function serviceOptionsQuery(Builder $query, ?Project $project): Builder
    {
        $selectedIds = $project?->exists
            ? $project->relatedServices()->pluck('services.id')->all()
            : [];

        return $query->where(function (Builder $query) use ($selectedIds): void {
            $query->published();

            if ($selectedIds !== []) {
                $query->orWhereIn('services.id', $selectedIds);
            }
        });
    }
}
