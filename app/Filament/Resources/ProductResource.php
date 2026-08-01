<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = Product::class;

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->shopEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('ویرایشگر محصول')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('محتوا')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('slug'))
                                    ? $set('slug', Str::slug($state ?? ''))
                                    : null),
                            Forms\Components\TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            Forms\Components\Select::make('product_category_id')
                                ->label('دسته‌بندی')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Textarea::make('excerpt')
                                ->rows(3)
                                ->helperText('خلاصه کوتاهی که در کارت محصول و در صورت خالی بودن توضیحات سئو نمایش داده می‌شود.')
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('content')
                                ->label('توضیحات')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('قیمت و موجودی')
                        ->schema([
                            Forms\Components\TextInput::make('price')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('IRT')
                                ->suffix('تومان')
                                ->default(0),
                            Forms\Components\TextInput::make('sale_price')
                                ->numeric()
                                ->minValue(0)
                                ->lt('price')
                                ->prefix('IRT')
                                ->suffix('تومان')
                                ->helperText('اختیاری است و باید از قیمت اصلی کمتر باشد.'),
                            Forms\Components\TextInput::make('sku')
                                ->label('شناسه محصول')
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            Forms\Components\Toggle::make('has_stock')
                                ->label('قابل خرید')
                                ->default(true),
                            Forms\Components\Select::make('stock_status')
                                ->required()
                                ->options([
                                    'in_stock' => 'موجود',
                                    'out_of_stock' => 'ناموجود',
                                ])
                                ->default('in_stock'),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('مشخصات')
                        ->schema([
                            Forms\Components\Repeater::make('specifications')
                                ->label('مشخصات ساختاریافته')
                                ->relationship('specifications')
                                ->schema([
                                    Forms\Components\TextInput::make('group_name')
                                        ->label('گروه')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('key')
                                        ->label('کلید')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('label')
                                        ->label('عنوان')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('value')
                                        ->label('مقدار')
                                        ->rows(2),
                                    Forms\Components\TextInput::make('unit')
                                        ->label('واحد')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('ترتیب نمایش')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['key'] ?? 'مشخصه')
                                ->orderColumn('sort_order')
                                ->reorderable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('اسناد')
                        ->schema([
                            Forms\Components\Repeater::make('documents')
                                ->label('فایل‌ها و اسناد محصول')
                                ->relationship('documents')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('عنوان فایل')
                                        ->maxLength(255),
                                    Forms\Components\FileUpload::make('file_path')
                                        ->label('فایل')
                                        ->disk('public')
                                        ->directory('product-documents')
                                        ->preserveFilenames()
                                        ->downloadable()
                                        ->openable(),
                                    Forms\Components\TextInput::make('external_url')
                                        ->label('نشانی خارجی فایل')
                                        ->url()
                                        ->maxLength(255)
                                        ->helperText('در صورت استفاده از فایل خارجی، نشانی کامل را وارد کنید.'),
                                    Forms\Components\TextInput::make('mime_type')
                                        ->label('نوع فایل')
                                        ->placeholder('application/pdf')
                                        ->maxLength(255),
                                    Forms\Components\Hidden::make('disk')
                                        ->default('public'),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('ترتیب نمایش')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['file_path'] ?? 'سند')
                                ->orderColumn('sort_order')
                                ->reorderable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('محصولات مرتبط')
                        ->schema([
                            Forms\Components\Repeater::make('related_products')
                                ->label('محصولات مرتبط')
                                ->schema([
                                    Forms\Components\Select::make('product_id')
                                        ->label('محصول')
                                        ->required()
                                        ->options(fn (?Product $record): array => Product::query()
                                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                            ->orderBy('title')
                                            ->pluck('title', 'id')
                                            ->all())
                                        ->searchable()
                                        ->preload()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('ترتیب نمایش')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Product $record): void {
                                    $set('related_products', $record
                                        ? $record->relatedProducts()
                                            ->get()
                                            ->map(fn (Product $related): array => [
                                                'product_id' => $related->getKey(),
                                                'sort_order' => (int) $related->pivot->sort_order,
                                            ])
                                            ->all()
                                        : []);
                                })
                                ->orderColumn('sort_order')
                                ->reorderable()
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
                                ->afterStateHydrated(function (Set $set, ?Product $record): void {
                                    $set(
                                        'featured_media_id',
                                        $record?->featuredImage()?->getCustomProperty('source_media_id')
                                            ?: ($record?->featuredImage() ? '__keep_existing__' : null),
                                    );
                                })
                                ->helperText('یک تصویر موجود را از کتابخانه رسانه انتخاب کنید.'),
                            Forms\Components\ViewField::make('gallery_media_ids')
                                ->label('گالری محصول')
                                ->view('filament.forms.components.media-library-multiple-picker')
                                ->viewData(fn (?Product $record): array => [
                                    'images' => $record
                                        ? static::mediaLibraryImageItemsWithCollection($record, 'gallery')
                                        : static::mediaLibraryImageItems(),
                                ])
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, ?Product $record): void {
                                    $set('gallery_media_ids', $record
                                        ? static::mediaLibraryCollectionState($record, 'gallery')
                                        : []);
                                })
                                ->helperText('تصاویر گالری را از کتابخانه رسانه انتخاب کنید.')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('سئو')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('عنوان سئو')
                                ->maxLength(70)
                                ->helperText('پیشنهاد می‌شود حداکثر ۷۰ نویسه باشد.'),
                            Forms\Components\Textarea::make('seo_description')
                                ->maxLength(160)
                                ->helperText('پیشنهاد می‌شود حداکثر ۱۶۰ نویسه باشد.')
                                ->rows(3)
                                ->columnSpanFull(),
                            Forms\Components\ViewField::make('seo_image')
                                ->label('تصویر شبکه‌های اجتماعی')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => [
                                    'images' => static::mediaLibraryImageItems(),
                                ])
                                ->helperText('اگر خالی باشد، تصویر شاخص استفاده می‌شود.')
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('robots_index')
                                ->label('اجازه ایندکس این محصول به موتورهای جست‌وجو')
                                ->default(true),
                            Forms\Components\Toggle::make('robots_follow')
                                ->label('اجازه دنبال کردن پیوندها به موتورهای جست‌وجو')
                                ->default(true),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('انتشار')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->required()
                                ->options([
                                    'draft' => 'پیش‌نویس',
                                    'published' => 'منتشرشده',
                                ])
                                ->default('draft'),
                            Forms\Components\DateTimePicker::make('published_at')
                                ->seconds(false)
                                ->helperText('برای انتشار فوری پس از انتخاب وضعیت «منتشرشده»، این فیلد را خالی بگذارید.'),
                            Forms\Components\Toggle::make('is_featured')
                                ->label('محصول ویژه')
                                ->default(false),
                            Forms\Components\TextInput::make('sort_order')
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
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('دسته‌بندی')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('قیمت')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state).' تومان')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('قیمت فروش')
                    ->formatStateUsing(fn (mixed $state): string => filled($state)
                        ? number_format((float) $state).' تومان'
                        : '—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('stock_status')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('ویژه')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'پیش‌نویس',
                    'published' => 'منتشرشده',
                ]),
                Tables\Filters\SelectFilter::make('category')->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('ویژه'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('پیش‌نمایش')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Product $record): string => route('admin.preview.products.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('viewPublic')
                    ->label('مشاهده در سایت')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Product $record): string => static::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Product $record): bool => static::isPubliclyVisible($record)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function publicUrl(Product $product): string
    {
        return route('shop.show', $product->slug);
    }

    public static function isPubliclyVisible(Product $product): bool
    {
        return $product->status === 'published'
            && (blank($product->published_at) || $product->published_at->lte(now()));
    }

    public static function syncRelatedProducts(Product $product, ?array $selection): void
    {
        $selectedIds = collect($selection ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['product_id'] ?? null))
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->reject(fn (int $id): bool => $id < 1 || $id === (int) $product->getKey())
            ->unique()
            ->values();

        $existingIds = Product::query()
            ->whereKey($selectedIds->all())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $syncData = collect($selection ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->reduce(function (array $syncData, array $item) use ($existingIds, $product): array {
                $relatedProductId = (int) ($item['product_id'] ?? 0);

                if (
                    $relatedProductId < 1
                    || $relatedProductId === (int) $product->getKey()
                    || ! in_array($relatedProductId, $existingIds, true)
                    || array_key_exists($relatedProductId, $syncData)
                ) {
                    return $syncData;
                }

                $syncData[$relatedProductId] = [
                    'sort_order' => max(0, (int) ($item['sort_order'] ?? 0)),
                ];

                return $syncData;
            }, []);

        $product->relatedProducts()->sync($syncData);
    }
}
