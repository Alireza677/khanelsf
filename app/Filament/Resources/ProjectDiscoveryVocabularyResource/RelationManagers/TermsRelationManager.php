<?php

namespace App\Filament\Resources\ProjectDiscoveryVocabularyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TermsRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    protected static ?string $title = 'گزینه‌های فیلتر';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('نام گزینه')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, Forms\Set $set) => $set('slug', Str::slug($state ?? ''))),
            Forms\Components\TextInput::make('slug')
                ->label('نامک')
                ->required()
                ->maxLength(255),
            Forms\Components\Toggle::make('is_active')->label('فعال')->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label('ترتیب نمایش')
                ->numeric()->minValue(0)->default(0)->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('گزینه')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('نامک'),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('افزودن گزینه')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('sort_order');
    }
}
