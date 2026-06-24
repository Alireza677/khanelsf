<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected static bool $canCreateAnother = false;

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'fi-page-editor-locked-scroll',
        ];
    }

    protected function afterCreate(): void
    {
        PageResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('ایجاد برگه');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'برگه ایجاد شد';
    }
}
