<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use App\Filament\Resources\ProjectResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return 'ایجاد پروژه';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'پروژه با موفقیت ایجاد شد.';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('ایجاد پروژه');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('ایجاد و افزودن پروژه دیگر');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function afterCreate(): void
    {
        ProjectResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }
}
