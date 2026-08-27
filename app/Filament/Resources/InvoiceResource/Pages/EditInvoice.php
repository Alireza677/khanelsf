<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource;
use App\Services\InvoiceTotalsCalculator;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status !== InvoiceStatus::Draft) {
            throw new HttpException(409);
        }

return [...$data, ...app(InvoiceTotalsCalculator::class)->calculate($this->record->items()->pluck('total_amount'), $data['discount_amount'] ?? 0, $data['tax_amount'] ?? 0)];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
