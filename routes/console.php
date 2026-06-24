<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:cms', function () {
    $this->info('Starter CMS is installed.');
});
