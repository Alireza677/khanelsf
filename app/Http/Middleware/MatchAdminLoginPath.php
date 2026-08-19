<?php

namespace App\Http\Middleware;

use App\Support\AdminLoginPath;
use Closure;
use Illuminate\Http\Request;

class MatchAdminLoginPath
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless(
            trim((string) $request->route('admin_login_path'), '/') === app(AdminLoginPath::class)->current(),
            404,
        );

        return $next($request);
    }
}
