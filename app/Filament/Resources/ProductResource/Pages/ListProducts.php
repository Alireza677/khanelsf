<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\ModuleService;
use App\Services\ProductImport\ProductImportService;
use App\Services\ProductImport\ProductImportTemplate;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('importProducts')
                ->label('درون‌ریزی محصولات')->icon('heroicon-o-arrow-up-tray')
                ->url(ProductResource::getUrl('import'))
                ->visible(fn (): bool => app(ModuleService::class)->shopEnabled() && ProductResource::canCreate()),
            Actions\Action::make('downloadProductTemplate')
                ->label('دریافت قالب اکسل محصولات')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn (): bool => app(ModuleService::class)->shopEnabled() && ProductResource::canCreate())
                ->action(function (ProductImportService $service, ProductImportTemplate $template) {
                    $service->authorize(auth()->user());

                    return $template->download();
                }),
        ];
    }
}
