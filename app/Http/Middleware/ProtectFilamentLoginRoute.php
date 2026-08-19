<?php

namespace App\Http\Middleware;

use App\Support\AdminLoginPath;
use Closure;
use Illuminate\Http\Request;

class ProtectFilamentLoginRoute
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->routeIs('filament.admin.auth.login')
            && app(AdminLoginPath::class)->current() !== AdminLoginPath::DEFAULT) {
            abort(404);
        }

        return $next($request);
    }
}
