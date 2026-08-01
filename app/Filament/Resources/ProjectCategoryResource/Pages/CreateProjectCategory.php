<?php

namespace App\Filament\Resources\ProjectCategoryResource\Pages;

use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use App\Filament\Resources\ProjectCategoryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectCategory extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = ProjectCategoryResource::class;

    public function getTitle(): string
    {
        return 'ایجاد دسته‌بندی پروژه';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'دسته‌بندی پروژه با موفقیت ایجاد شد.';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('ایجاد دسته‌بندی');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('ایجاد و افزودن دسته‌بندی دیگر');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }
}
