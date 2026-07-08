<?php

use App\Providers\BlockServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ShopServiceProvider;

return [
    BlockServiceProvider::class,
    ShopServiceProvider::class,
    AdminPanelProvider::class,
];
