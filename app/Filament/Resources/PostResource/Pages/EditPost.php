<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function afterSave(): void
    {
        PostResource::syncFeaturedImage(
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
                ->url(fn (): string => route('admin.preview.posts.show', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('viewPublic')
                ->label('View public post')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => PostResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => PostResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
