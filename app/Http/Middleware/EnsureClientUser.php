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
        $user = $guard->user()?->fresh();

        if (! $user?->isClient() || ! $user->isActive()) {
            $guard->logout();

            $checkout = $request->routeIs('checkout.create', 'checkout.store');
            if ($checkout) {
                $request->session()->put('url.intended', route('checkout.create'));
            }
            if ($request->expectsJson()) {
                return response()->json(['code' => 'unauthenticated', 'message' => 'برای ادامه، وارد حساب فعال خود شوید.'], 401);
            }

            return redirect()->route('login', $checkout ? ['continue' => 'checkout'] : [])->withErrors([
                'mobile' => 'برای ادامه، وارد حساب فعال خود شوید.',
            ]);
        }

        $guard->setUser($user);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
