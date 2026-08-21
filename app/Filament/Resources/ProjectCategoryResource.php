<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProjectCategoryResource\Pages;
use App\Models\ProjectCategory;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProjectCategoryResource extends Resource
{
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = ProjectCategory::class;

    protected static ?string $navigationGroup = 'پروژه‌ها';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'دسته‌های پروژه';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->projectsEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('ویرایشگر دسته‌بندی پروژه')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('محتوا')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('نام دسته‌بندی')
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
                            Forms\Components\Textarea::make('description')
                                ->label('توضیحات دسته‌بندی')
                                ->rows(4)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('سئو')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('عنوان سئو')
                                ->maxLength(70)
                                ->helperText('حداکثر ۷۰ نویسه پیشنهاد می‌شود. در صورت خالی بودن، نام دسته‌بندی استفاده خواهد شد.'),
                            Forms\Components\Textarea::make('seo_description')
                                ->label('توضیحات سئو')
                                ->maxLength(160)
                                ->helperText('حداکثر ۱۶۰ نویسه پیشنهاد می‌شود. در صورت خالی بودن، توضیحات دسته‌بندی یا تنظیمات پیش‌فرض سایت استفاده خواهد شد.')
                                ->rows(3)
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('seo_image')
                                ->label('تصویر اشتراک‌گذاری')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->helperText('تصویر اختیاری پیش‌نمایش شبکه‌های اجتماعی برای صفحه آرشیو این دسته‌بندی.')
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('robots_index')
                                ->label('اجازه فهرست‌شدن دسته‌بندی در موتورهای جست‌وجو')
                                ->default(true),
                            Forms\Components\Toggle::make('robots_follow')
                                ->label('اجازه دنبال‌کردن پیوندها توسط موتورهای جست‌وجو')
                                ->default(true),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('انتشار')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label('وضعیت')
                                ->required()
                                ->options([
                                    'active' => 'فعال',
                                    'inactive' => 'غیرفعال',
                                ])
                                ->default('active'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('نام دسته‌بندی')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('نامک')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('projects_count')
                    ->counts('projects')
                    ->label('تعداد پروژه‌ها')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'فعال',
                        'inactive' => 'غیرفعال',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب نمایش')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین ویرایش')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غیرفعال',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->modalHeading('حذف دسته‌بندی پروژه')
                    ->modalDescription('آیا از حذف این دسته‌بندی اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                    ->modalSubmitActionLabel('بله، حذف شود')
                    ->successNotificationTitle('دسته‌بندی پروژه حذف شد.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف دسته‌بندی‌های انتخاب‌شده')
                        ->modalHeading('حذف دسته‌بندی‌های انتخاب‌شده')
                        ->modalDescription('آیا از حذف دسته‌بندی‌های انتخاب‌شده اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                        ->modalSubmitActionLabel('بله، حذف شوند')
                        ->successNotificationTitle('دسته‌بندی‌های انتخاب‌شده حذف شدند.'),
                ]),
            ])
            ->emptyStateHeading('هنوز دسته‌بندی پروژه‌ای ثبت نشده است')
            ->emptyStateDescription('برای گروه‌بندی پروژه‌ها، نخستین دسته‌بندی را ایجاد کنید.')
            ->emptyStateIcon('heroicon-o-folder');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectCategories::route('/'),
            'create' => Pages\CreateProjectCategory::route('/create'),
            'edit' => Pages\EditProjectCategory::route('/{record}/edit'),
        ];
    }
}
