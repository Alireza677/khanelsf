<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentHealthOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make('محتوای پیش‌نویس', $this->draftContentCount())
                ->description('برگه‌ها، نوشته‌ها، پروژه‌ها و گالری‌های در انتظار انتشار')
                ->icon('heroicon-o-pencil-square')
                ->color('warning'),
            Stat::make('توضیحات سئو ناقص', $this->missingSeoDescriptionCount())
                ->description('محتوای منتشرشده یا پیش‌نویس بدون توضیح سئوی اختصاصی')
                ->icon('heroicon-o-magnifying-glass')
                ->color('danger'),
            Stat::make('پیام‌های تماس جدید', ContactMessage::query()->where('status', 'new')->count())
                ->description('پیام‌های خوانده‌نشده')
                ->icon('heroicon-o-inbox')
                ->color('info'),
        ];
    }

    private function draftContentCount(): int
    {
        return Page::query()->where('status', 'draft')->count()
            + Post::query()->where('status', 'draft')->count()
            + Gallery::query()->where('status', 'draft')->count()
            + Project::query()->where('status', 'draft')->count();
    }

    private function missingSeoDescriptionCount(): int
    {
        return Page::query()->where(fn ($query) => $query->whereNull('seo_description')->orWhere('seo_description', ''))->count()
            + Post::query()->where(fn ($query) => $query->whereNull('seo_description')->orWhere('seo_description', ''))->count()
            + Gallery::query()->where(fn ($query) => $query->whereNull('seo_description')->orWhere('seo_description', ''))->count()
            + Project::query()->where(fn ($query) => $query->whereNull('seo_description')->orWhere('seo_description', ''))->count();
    }
}
