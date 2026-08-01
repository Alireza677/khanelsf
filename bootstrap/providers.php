<?php

use App\Providers\ActionServiceProvider;
use App\Providers\BlockServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FormServiceProvider;
use App\Providers\InternalLinkServiceProvider;
use App\Providers\NavigationServiceProvider;
use App\Providers\PageServiceProvider;
use App\Providers\ProjectServiceProvider;
use App\Providers\ServiceServiceProvider;
use App\Providers\ShopServiceProvider;
use App\Providers\TemplateRecipeServiceProvider;

return [
    ActionServiceProvider::class,
    InternalLinkServiceProvider::class,
    PageServiceProvider::class,
    ProjectServiceProvider::class,
    BlockServiceProvider::class,
    NavigationServiceProvider::class,
    ShopServiceProvider::class,
    ServiceServiceProvider::class,
    FormServiceProvider::class,
    TemplateRecipeServiceProvider::class,
    AdminPanelProvider::class,
];
