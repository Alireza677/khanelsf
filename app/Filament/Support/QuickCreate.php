<?php

namespace App\Filament\Support;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\MediaResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ServiceResource;

final class QuickCreate
{
    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public function items(): array
    {
        return collect([
            $this->resourceItem('برگه', PageResource::class, 'create', 'heroicon-o-document'),
            $this->resourceItem('نوشته', PostResource::class, 'create', 'heroicon-o-pencil-square'),
            $this->resourceItem(
                'پروژه عمومی',
                ProjectResource::class,
                'create',
                'heroicon-o-briefcase',
                ProjectResource::shouldRegisterNavigation(),
            ),
            // Public delivery is deliberately not an admin availability condition.
            $this->resourceItem('خدمت', ServiceResource::class, 'create', 'heroicon-o-wrench-screwdriver'),
            $this->resourceItem(
                'محصول',
                ProductResource::class,
                'create',
                'heroicon-o-shopping-bag',
                ProductResource::shouldRegisterNavigation(),
            ),
            $this->resourceItem('فرم', FormResource::class, 'create', 'heroicon-o-clipboard-document-list'),
            $this->resourceItem('آپلود رسانه', MediaResource::class, 'upload', 'heroicon-o-arrow-up-tray'),
        ])->filter()->values()->all();
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return array{label: string, url: string, icon: string}|null
     */
    private function resourceItem(
        string $label,
        string $resource,
        string $page,
        string $icon,
        bool $moduleVisible = true,
    ): ?array {
        if (! $moduleVisible || ! $resource::canCreate()) {
            return null;
        }

        return [
            'label' => $label,
            'url' => $resource::getUrl($page),
            'icon' => $icon,
        ];
    }
}
