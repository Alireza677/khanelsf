<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CrmCustomers extends Page
{
    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'مشتریان';

    protected static ?string $title = 'مشتریان';

    protected static ?string $slug = 'crm/customers';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.crm-placeholder';
}
