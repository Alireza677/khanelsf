<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Server-side allowlist, independent of disabled fields or forged Livewire state.
        $record->update(['admin_note' => $data['admin_note'] ?? null]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('چاپ فاکتور')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('admin.orders.print', $this->record))
                ->openUrlInNewTab(),
            Actions\ViewAction::make()->label('مشاهده جزئیات'),
        ];
    }
}
