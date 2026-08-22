<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:cms', function () {
    $this->info('Starter CMS is installed.');
});

Schedule::command('backup:cleanup-orphans')->dailyAt('04:00')->withoutOverlapping();
