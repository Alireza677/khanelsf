<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use App\Filament\Resources\ServiceOfferingResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OfferingsRelationManager extends RelationManager
{
    protected static string $relationship = 'offerings';

    protected static ?string $title = 'بسته‌های خدمت';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('بسته'),
            Tables\Columns\TextColumn::make('code')->label('کد'),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->formatStateUsing(fn ($state) => ServiceOfferingResource::statuses()[$state]),
        ])->headerActions([
            Tables\Actions\Action::make('createOffering')->label('افزودن بسته')->url(fn () => ServiceOfferingResource::getUrl('create', ['service' => $this->getOwnerRecord()->id]))->visible(fn () => ServiceOfferingResource::canCreate()),
        ])->actions([
            Tables\Actions\Action::make('manage')->label('مدیریت بسته و نسخه‌ها')->url(fn ($record) => ServiceOfferingResource::getUrl('view', ['record' => $record])),
        ])->bulkActions([]);
    }
}
