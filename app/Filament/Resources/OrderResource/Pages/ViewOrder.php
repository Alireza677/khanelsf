<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('چاپ فاکتور')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('admin.orders.print', $this->record))
                ->openUrlInNewTab(),
            Actions\ActionGroup::make([
                ...OrderResource::operationActions(Actions\Action::class),
                Actions\EditAction::make()->label('ویرایش یادداشت داخلی'),
            ])->label('عملیات')->icon('heroicon-m-ellipsis-vertical')->button(),
        ];
    }
}
