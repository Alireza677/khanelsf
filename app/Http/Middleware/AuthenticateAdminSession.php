<?php

namespace App\Http\Middleware;

use App\Support\AdminLoginPath;
use Filament\Http\Middleware\AuthenticateSession;

class AuthenticateAdminSession extends AuthenticateSession
{
    protected function redirectTo($request): ?string
    {
        return app(AdminLoginPath::class)->url();
    }
}
