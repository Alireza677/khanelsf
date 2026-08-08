<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProjectDiscoveryVocabularyResource\Pages;
use App\Filament\Resources\ProjectDiscoveryVocabularyResource\RelationManagers\TermsRelationManager;
use App\Models\ProjectDiscoveryVocabulary;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProjectDiscoveryVocabularyResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ProjectDiscoveryVocabulary::class;

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $slug = 'project-gallery-filters';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->projectsEnabled()
            && app(ModuleService::class)->galleriesEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('نام گروه فیلتر')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, Forms\Set $set) => $set('slug', Str::slug($state ?? ''))),
            Forms\Components\TextInput::make('slug')
                ->label('نامک')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\Toggle::make('is_active')->label('فعال')->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label('ترتیب نمایش')
                ->numeric()->minValue(0)->default(0)->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('گروه فیلتر')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('نامک')->searchable(),
                Tables\Columns\TextColumn::make('terms_count')->label('تعداد گزینه‌ها')->counts('terms'),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getRelations(): array
    {
        return [TermsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectDiscoveryVocabularies::route('/'),
            'create' => Pages\CreateProjectDiscoveryVocabulary::route('/create'),
            'edit' => Pages\EditProjectDiscoveryVocabulary::route('/{record}/edit'),
        ];
    }
}
