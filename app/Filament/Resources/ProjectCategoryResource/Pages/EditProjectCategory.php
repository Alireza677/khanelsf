<?php

namespace App\Filament\Resources\ProjectCategoryResource\Pages;

use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use App\Filament\Resources\ProjectCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectCategory extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = ProjectCategoryResource::class;

    public function getTitle(): string
    {
        return 'ویرایش دسته‌بندی پروژه';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تغییرات دسته‌بندی پروژه ذخیره شد.';
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()->label('ذخیره تغییرات');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('حذف دسته‌بندی')
                ->modalHeading('حذف دسته‌بندی پروژه')
                ->modalDescription('آیا از حذف این دسته‌بندی اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                ->modalSubmitActionLabel('بله، حذف شود')
                ->successNotificationTitle('دسته‌بندی پروژه حذف شد.'),
        ];
    }
}
