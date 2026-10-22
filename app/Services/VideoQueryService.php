<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;

final class VideoQueryService
{
    public function __construct(private readonly VideoLibrarySettings $settings) {}

    public function archiveQuery(): Builder
    {
        return Video::query()->published()
            ->when(! $this->settings->enabled(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->with(['categories', 'media', 'mediaUsages.media'])
            ->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function featureGridQuery(?int $categoryId = null, string $sort = 'newest'): Builder
    {
        $query = $this->archiveQuery()->when($categoryId, fn (Builder $query) => $query
            ->whereHas('categories', fn (Builder $category) => $category->whereKey($categoryId)));
        if ($sort === 'manual') {
            return $query;
        }
        $direction = $sort === 'oldest' ? 'asc' : 'desc';

        return $query->reorder()->orderByRaw('COALESCE(videos.published_at, videos.created_at) '.$direction)->orderBy('id', $direction);
    }
}
