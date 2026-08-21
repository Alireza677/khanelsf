<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductCategoryResource extends Resource
{
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Product Categories';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->shopEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Product category editor')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Content')
                        ->schema([
                            Forms\Components\TextInput::make('name')
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
                            Forms\Components\Textarea::make('description')->rows(4)->columnSpanFull(),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('SEO')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')->label('SEO title')->maxLength(70),
                            Forms\Components\Textarea::make('seo_description')->maxLength(160)->rows(3)->columnSpanFull(),
                            Forms\Components\ViewField::make('seo_image')
                                ->label('Social image URL')
                                ->view('filament.forms.components.media-library-url-picker')
                                ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('robots_index')->label('Allow indexing')->default(true),
                            Forms\Components\Toggle::make('robots_follow')->label('Allow following links')->default(true),
                        ])
                        ->columns(2),
                    Forms\Components\Tabs\Tab::make('Publishing')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->required()
                                ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                                ->default('active'),
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
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('products_count')->counts('products')->label('Products')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->jalaliDateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive']),
            ])
            ->actions([
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
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
