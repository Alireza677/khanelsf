<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Models\ClientProjectActivity;
use App\Models\InvoiceItem;
use App\Services\BillableActivityQuery;
use App\Services\DraftInvoiceItems;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'آیتم‌های فاکتور';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('activity_title_snapshot')->label('فعالیت'),
            Tables\Columns\TextColumn::make('project_title_snapshot')->label('پروژه'),
            Tables\Columns\TextColumn::make('service_name_snapshot')->label('خدمت')->placeholder('—'),
            Tables\Columns\TextColumn::make('quantity')->label('مقدار')->placeholder('—'),
            Tables\Columns\TextColumn::make('unit_price')->label('قیمت واحد')->placeholder('—'),
            Tables\Columns\TextColumn::make('total_amount')->label('مبلغ'),
        ])->headerActions([
            Tables\Actions\Action::make('addActivity')->label('افزودن فعالیت')->visible(fn () => $this->getOwnerRecord()->status === InvoiceStatus::Draft)
                ->form([Forms\Components\Select::make('activity_id')->label('فعالیت')->options(fn () => app(BillableActivityQuery::class)->for($this->getOwnerRecord()->customer, $this->getOwnerRecord()->period_start, $this->getOwnerRecord()->period_end)->when($this->getOwnerRecord()->client_project_cycle_id, fn ($query, $cycleId) => $query->where('client_project_cycle_id', $cycleId))->pluck('title', 'id'))->required()->searchable()])
                ->action(fn (array $data) => app(DraftInvoiceItems::class)->add($this->getOwnerRecord(), ClientProjectActivity::findOrFail($data['activity_id']))),
        ])->actions([
            Tables\Actions\Action::make('remove')->label('حذف')->color('danger')->requiresConfirmation()->visible(fn () => $this->getOwnerRecord()->status === InvoiceStatus::Draft)->action(fn (InvoiceItem $record) => app(DraftInvoiceItems::class)->remove($record)),
        ])->bulkActions([]);
    }
}
