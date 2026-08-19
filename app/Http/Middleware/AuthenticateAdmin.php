<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

class AuthenticateAdmin extends Authenticate
{
    protected function unauthenticated($request, array $guards): never
    {
        abort(404);
    }
}
