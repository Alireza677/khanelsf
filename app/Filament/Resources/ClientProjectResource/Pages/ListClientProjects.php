<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectActivityResource;
use App\Filament\Resources\ClientProjectCycleResource;
use App\Filament\Resources\ClientProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientProjects extends ListRecords
{
    protected static string $resource = ClientProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('پروژه جدید'),
            Actions\Action::make('allActivities')->label('فعالیت‌های زمانی همه پروژه‌ها')
                ->url(ClientProjectActivityResource::getUrl('index'))
                ->visible(fn (): bool => ClientProjectActivityResource::canViewAny())
                ->link()->color('gray'),
            Actions\Action::make('allCycles')->label('دوره‌های همه پروژه‌ها')
                ->url(ClientProjectCycleResource::getUrl('index'))
                ->visible(fn (): bool => ClientProjectCycleResource::canViewAny())
                ->link()->color('gray'),
        ];
    }
}
