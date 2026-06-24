<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Filament\Resources\GalleryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function afterSave(): void
    {
        GalleryResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('admin.preview.galleries.show', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('viewPublic')
                ->label('View public gallery')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => GalleryResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => GalleryResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
