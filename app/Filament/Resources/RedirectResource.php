<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class RedirectResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = Redirect::class;

    protected static ?string $navigationGroup = 'SEO';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('source_path')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Path only, for example /old-page. Admin, sitemap, robots, build, and storage paths are ignored by the resolver.')
                    ->rules([
                        fn (?Redirect $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                            $source = Redirect::normalizePath((string) $value);

                            $exists = Redirect::query()
                                ->where('source_path', $source)
                                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($exists) {
                                $fail('This source path already has a redirect.');
                            }
                        },
                    ])
                    ->dehydrateStateUsing(fn (string $state): string => Redirect::normalizePath($state)),
                Forms\Components\TextInput::make('target_url')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Internal path like /new-page or a full URL.')
                    ->rules([
                        fn (Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $source = Redirect::normalizePath((string) $get('source_path'));
                            $target = (str_starts_with((string) $value, 'http://') || str_starts_with((string) $value, 'https://'))
                                ? (parse_url((string) $value, PHP_URL_PATH) ?: '/')
                                : Redirect::normalizePath((string) $value);

                            if ($source === $target) {
                                $fail('The target URL must be different from the source path.');
                            }
                        },
                    ]),
                Forms\Components\Select::make('status_code')
                    ->required()
                    ->options([
                        301 => '301 Permanent',
                        302 => '302 Temporary',
                    ])
                    ->default(301)
                    ->rules([Rule::in([301, 302])])
                    ->helperText('Use 301 for permanent moves and 302 for temporary redirects.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Forms\Components\Textarea::make('note')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('source_path')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('target_url')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('status_code')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
                Tables\Columns\TextColumn::make('hits_count')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('last_hit_at')->jalaliDateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->jalaliDateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\SelectFilter::make('status_code')
                    ->options([
                        301 => '301',
                        302 => '302',
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
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
