<?php

namespace App\Providers;

use App\Services\EditorHistory\CacheEditorHistoryStore;
use App\Services\EditorHistory\EditorHistoryStore;
use Illuminate\Support\ServiceProvider;

final class EditorHistoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EditorHistoryStore::class, CacheEditorHistoryStore::class);
    }
}
