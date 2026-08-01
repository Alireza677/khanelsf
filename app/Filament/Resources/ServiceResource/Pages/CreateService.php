<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    public function getTitle(): string
    {
        return 'ایجاد خدمت';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'خدمت با موفقیت ایجاد شد.';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('ایجاد خدمت');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('ایجاد و افزودن خدمت دیگر');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function afterCreate(): void
    {
        ServiceResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
        ServiceResource::syncMediaLibraryCollection(
            $this->record,
            'gallery',
            data_get($this->form->getRawState(), 'gallery_media_ids', []),
        );
    }
}
