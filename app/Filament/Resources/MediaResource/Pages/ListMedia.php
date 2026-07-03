<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    #[Url(as: 'view')]
    public string $mediaView = 'list';

    public function mount(): void
    {
        parent::mount();

        if (! in_array($this->mediaView, ['list', 'grid'], true)) {
            $this->mediaView = 'list';
        }
    }

    public function setMediaView(string $view): void
    {
        if (! in_array($view, ['list', 'grid'], true)) {
            return;
        }

        $this->mediaView = $view;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')
                ->label('بارگذاری رسانه')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(static::getResource()::getUrl('upload')),
        ];
    }
}
