<?php

use App\Providers\ActionServiceProvider;
use App\Providers\AdminLoginServiceProvider;
use App\Providers\BlockServiceProvider;
use App\Providers\EditorHistoryServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FormServiceProvider;
use App\Providers\InternalLinkServiceProvider;
use App\Providers\NavigationServiceProvider;
use App\Providers\PageServiceProvider;
use App\Providers\PostServiceProvider;
use App\Providers\ProjectServiceProvider;
use App\Providers\ServiceServiceProvider;
use App\Providers\ShopServiceProvider;
use App\Providers\TemplateRecipeServiceProvider;
use App\Providers\TestingDatabaseSafetyServiceProvider;

return [
    AdminLoginServiceProvider::class,
    ActionServiceProvider::class,
    InternalLinkServiceProvider::class,
    PageServiceProvider::class,
    PostServiceProvider::class,
    ProjectServiceProvider::class,
    BlockServiceProvider::class,
    EditorHistoryServiceProvider::class,
    NavigationServiceProvider::class,
    ShopServiceProvider::class,
    ServiceServiceProvider::class,
    FormServiceProvider::class,
    TemplateRecipeServiceProvider::class,
    TestingDatabaseSafetyServiceProvider::class,
    AdminPanelProvider::class,
];
