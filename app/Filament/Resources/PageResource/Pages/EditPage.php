<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = PageResource::class;

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'fi-page-editor-locked-scroll',
        ];
    }

    protected function afterSave(): void
    {
        PageResource::syncFeaturedImage(
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
                ->url(fn (): string => route('admin.preview.pages.show', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('viewPublic')
                ->label('مشاهده برگه')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => PageResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => PageResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make()
                ->label('حذف')
                ->modalHeading('حذف برگه')
                ->modalSubmitActionLabel('حذف')
                ->modalCancelActionLabel('انصراف'),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('ذخیره تغییرات');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'برگه ذخیره شد';
    }
}
