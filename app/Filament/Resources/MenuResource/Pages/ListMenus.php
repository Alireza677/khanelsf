<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use App\Models\Menu;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('ساخت منو')
                ->form(MenuResource::quickCreateForm())
                ->mutateFormDataUsing(MenuResource::prepareQuickCreateData(...))
                ->createAnother(false)
                ->successRedirectUrl(fn (Menu $record): string => MenuResource::getUrl('edit', ['record' => $record])),
        ];
    }
}
