<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CrmPartners extends Page
{
    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'همکاران';

    protected static ?string $title = 'همکاران';

    protected static ?string $slug = 'crm/partners';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.crm-placeholder';
}
