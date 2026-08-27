<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\ClientProjectCycle;
use App\Services\ProjectCycleInvoiceGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ProjectCycleInvoiceGenerator::class)->generate(ClientProjectCycle::findOrFail($data['client_project_cycle_id']), auth()->user());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'پیش‌نویس فاکتور ساخته شد';
    }
}
