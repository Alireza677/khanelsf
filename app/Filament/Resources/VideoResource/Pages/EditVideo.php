<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVideo extends EditRecord
{
    protected static string $resource = VideoResource::class;

    protected function afterSave(): void
    {
        VideoResource::syncFeaturedImage($this->record, data_get($this->form->getRawState(), 'featured_media_id'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refreshMetadata')->label('به‌روزرسانی اطلاعات آپارات')->icon('heroicon-o-arrow-path')
                ->authorize(fn (): bool => VideoResource::canEdit($this->record))
                ->action(function (): void {
                    VideoResource::refreshProviderMetadata($this->record);
                    if (blank($this->data['duration_seconds'] ?? null)) {
                        $this->data['duration_seconds'] = $this->record->formattedDuration();
                    }
                }),
            Actions\DeleteAction::make()->label('حذف'),
        ];
    }
}
