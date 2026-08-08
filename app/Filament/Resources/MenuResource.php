<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MenuResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = Menu::class;

    protected static ?string $navigationGroup = 'Site Settings';

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
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
            Forms\Components\TextInput::make('location')
                ->maxLength(255)
                ->helperText('Example: header, footer, sidebar'),
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
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
                Tables\Filters\SelectFilter::make('location')
                    ->options(fn () => Menu::query()
                        ->whereNotNull('location')
                        ->distinct()
                        ->pluck('location', 'location')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('مدیریت منو'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('هنوز منویی ساخته نشده است')
            ->emptyStateDescription('نام اولین منو را وارد کنید تا مدیریت آیتم‌های آن را از همین بخش شروع کنید.')
            ->emptyStateIcon('heroicon-o-bars-3')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('ساخت اولین منو')
                    ->form(static::quickCreateForm())
                    ->mutateFormDataUsing(static::prepareQuickCreateData(...))
                    ->createAnother(false)
                    ->successRedirectUrl(fn (Menu $record): string => static::getUrl('edit', ['record' => $record])),
            ]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function quickCreateForm(): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->label('نام منو')
                ->required()
                ->maxLength(255)
                ->autofocus(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareQuickCreateData(array $data): array
    {
        $baseSlug = Str::slug((string) $data['title']) ?: 'menu';
        $slug = $baseSlug;
        $suffix = 2;

        while (Menu::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return [
            ...$data,
            'slug' => $slug,
            'status' => 'active',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
