<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return 'ویرایش پروژه';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تغییرات پروژه ذخیره شد.';
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
        ProjectResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('پیش‌نمایش')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('admin.preview.projects.show', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('viewPublic')
                ->label('مشاهده پروژه در سایت')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => ProjectResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => ProjectResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make()
                ->label('حذف پروژه')
                ->modalHeading('حذف پروژه')
                ->modalDescription('آیا از حذف این پروژه اطمینان دارید؟ این عملیات قابل بازگشت نیست.')
                ->modalSubmitActionLabel('بله، حذف شود')
                ->successNotificationTitle('پروژه حذف شد.'),
        ];
    }
}
