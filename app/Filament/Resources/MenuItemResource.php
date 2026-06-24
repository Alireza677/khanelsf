<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationGroup = 'Site Settings';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('menu_id')
                ->relationship('menu', 'title')
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),
            Forms\Components\Select::make('parent_id')
                ->label('Parent item')
                ->options(fn (Get $get, ?MenuItem $record): array => MenuItem::query()
                    ->where('menu_id', $get('menu_id'))
                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->pluck('title', 'id')
                    ->all())
                ->disabled(fn (Get $get): bool => blank($get('menu_id')))
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('url')
                ->maxLength(255)
                ->placeholder('/about'),
            Forms\Components\Placeholder::make('module_disabled_warning')
                ->label('Frontend visibility')
                ->content('This item points to a disabled module and is hidden on the public website. Visitors may see 404 if they open this URL directly.')
                ->visible(fn (Get $get): bool => ! app(ModuleService::class)->urlIsVisible($get('url')))
                ->columnSpanFull(),
            Forms\Components\Select::make('target')
                ->required()
                ->options([
                    '_self' => 'Same tab',
                    '_blank' => 'New tab',
                ])
                ->default('_self'),
            Forms\Components\TextInput::make('sort_order')
                ->required()
                ->numeric()
                ->minValue(0)
                ->default(0),
            Forms\Components\Select::make('status')
                ->required()
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ])
                ->default('active'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('menu.title')
                    ->label('Menu')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Parent')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('frontend_visibility')
                    ->label('Frontend')
                    ->state(fn (MenuItem $record): string => static::frontendVisibilityLabel($record))
                    ->badge()
                    ->color(fn (MenuItem $record): string => static::frontendVisibilityColor($record))
                    ->sortable(false),
                Tables\Columns\TextColumn::make('target')
                    ->badge(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu')
                    ->relationship('menu', 'title'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
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
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }

    private static function frontendVisibilityLabel(MenuItem $record): string
    {
        if ($record->status !== 'active') {
            return 'Inactive';
        }

        if (! app(ModuleService::class)->urlIsVisible($record->url)) {
            return 'Disabled module';
        }

        return 'Visible';
    }

    private static function frontendVisibilityColor(MenuItem $record): string
    {
        return match (static::frontendVisibilityLabel($record)) {
            'Visible' => 'success',
            'Disabled module' => 'warning',
            default => 'gray',
        };
    }
}
