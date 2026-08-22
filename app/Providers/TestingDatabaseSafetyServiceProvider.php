<?php

namespace App\Providers;

use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class TestingDatabaseSafetyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            FreshCommand::prohibit();
            RefreshCommand::prohibit();
            ResetCommand::prohibit();
            RollbackCommand::prohibit();
            WipeCommand::prohibit();
        }

        if (! $this->app->environment('testing')) {
            return;
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        $usesInMemorySqlite = $driver === 'sqlite' && $database === ':memory:';
        $usesDedicatedTestingDatabase = str_contains(strtolower($database), '_testing');

        if (! $usesInMemorySqlite && ! $usesDedicatedTestingDatabase) {
            throw new RuntimeException(
                'Unsafe testing database configuration: use SQLite :memory: or a dedicated database whose name contains _testing.'
            );
        }
    }
}
