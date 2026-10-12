<?php

namespace App\Filament\Resources\ServiceOfferingResource\RelationManagers;

use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceOfferingVersionResource;
use App\Models\ServiceOfferingVersion;
use App\Services\ServiceOfferingPublisher;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'نسخه‌های بسته';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('version')->label('نسخه')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->formatStateUsing(fn ($state) => $state === 'published' ? 'منتشرشده' : 'پیش‌نویس')->badge(),
            Tables\Columns\TextColumn::make('amount')->label('مبلغ'),
            Tables\Columns\TextColumn::make('currency_code')->label('واحد پول'),
            Tables\Columns\TextColumn::make('published_at')->label('انتشار')->jalaliDateTime(),
        ])->headerActions([
            Tables\Actions\Action::make('newDraft')->label('نسخه جدید — مدیریت پیشرفته')->color('gray')->visible(fn () => $this->getOwnerRecord()->status !== 'archived' && ServiceOfferingResource::canEdit($this->getOwnerRecord()))
                ->action(function (): void {
                    $version = app(ServiceOfferingPublisher::class)->newDraft($this->getOwnerRecord(), auth()->user());
                    $this->redirect(ServiceOfferingVersionResource::getUrl('edit', ['record' => $version]));
                }),
        ])->actions([
            Tables\Actions\Action::make('view')->label('مشاهده')->url(fn ($record) => ServiceOfferingVersionResource::getUrl('view', ['record' => $record])),
            Tables\Actions\Action::make('edit')->label('ویرایش پیش‌نویس — پیشرفته')->visible(fn (ServiceOfferingVersion $record) => $this->getOwnerRecord()->status !== 'archived' && ServiceOfferingVersionResource::canEdit($record))->url(fn ($record) => ServiceOfferingVersionResource::getUrl('edit', ['record' => $record])),
        ])->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status === 'archived';
    }
}
