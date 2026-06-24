<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CrmForms extends Page
{
    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'فرم ها';

    protected static ?string $title = 'فرم ها';

    protected static ?string $slug = 'crm/forms';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.crm-placeholder';
}
