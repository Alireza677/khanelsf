<?php

use App\Services\ClientProjectCycleReconciler;
use App\Services\RecalculateClientProjectCycle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:cms', function () {
    $this->info('Starter CMS is installed.');
});

Artisan::command('client-project-cycles:refresh-overdue', function () {
    $count = app(RecalculateClientProjectCycle::class)->refreshOverdue();
    $ensured = app(ClientProjectCycleReconciler::class)->ensureActiveRecurringCycles();
    $this->info("{$count} project cycle(s) marked overdue.");
    $this->info("{$ensured} active recurring project cycle(s) ensured.");
});

Schedule::command('backup:cleanup-orphans')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('client-project-cycles:refresh-overdue')->dailyAt('00:10')->withoutOverlapping();
