<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('client');
        $user = $guard->user();

        if (! $user?->isClient() || ! $user->isActive()) {
            $guard->logout();

            return redirect()->route('login')->withErrors([
                'mobile' => 'Access to the client portal is not authorized.',
            ]);
        }

        return $next($request);
    }
}
