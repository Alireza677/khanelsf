<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    public function getTitle(): string
    {
        return 'ویرایش خدمت';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تغییرات خدمت ذخیره شد.';
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()->label('ذخیره تغییرات');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function afterSave(): void
    {
        if (ServiceResource::sectionEnabled('media')) {
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('حذف خدمت')
                ->modalHeading('حذف خدمت')
                ->modalDescription('آیا از حذف این خدمت اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                ->modalSubmitActionLabel('بله، حذف شود')
                ->successNotificationTitle('خدمت حذف شد.'),
        ];
    }
}
