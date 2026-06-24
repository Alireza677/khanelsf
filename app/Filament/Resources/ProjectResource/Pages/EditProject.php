<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

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
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('admin.preview.projects.show', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('viewPublic')
                ->label('View public project')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => ProjectResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => ProjectResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
