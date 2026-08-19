<?php

namespace App\Http\Middleware;

use App\Support\AdminLoginPath;
use Filament\Http\Middleware\Authenticate;

class AuthenticateAdmin extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return app(AdminLoginPath::class)->url();
    }
}
