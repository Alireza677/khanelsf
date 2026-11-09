<?php

use App\Http\Middleware\EnsureClientUser;
use App\Http\Middleware\EnsureCustomerServiceCapability;
use App\Http\Middleware\LogLivewireRequests;
use App\Http\Middleware\ResolveRedirects;
use App\Http\Middleware\ShareClientPortalContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestsDuringMaintenance([
            'backup-restore-progress/*',
        ]);
        $middleware->redirectGuestsTo(function ($request): string {
            if ($request->is('admin', 'admin/*')) {
                abort(404);
            }

            return route('login', $request->routeIs('checkout.create', 'checkout.store') ? ['continue' => 'checkout'] : []);
        });
        $middleware->alias([
            'client' => EnsureClientUser::class,
            'client.context' => ShareClientPortalContext::class,
            'client.service' => EnsureCustomerServiceCapability::class,
        ]);
        $middleware->web(append: [
            ResolveRedirects::class,
            // TEMP DEBUG - remove after production save issue is fixed.
            LogLivewireRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Public modal transport; retain the framework status and rate-limit headers.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->expectsJson() || ! $request->routeIs('client.login.store', 'client.register.store')
                || ! in_array($exception->getStatusCode(), [419, 429], true)) {
                return null;
            }
            $expired = $exception->getStatusCode() === 419;

            return response()->json([
                'code' => $expired ? 'session_expired' : 'rate_limited',
                'message' => $expired
                    ? __('Page Expired')
                    : __('Too Many Requests'),
            ], $exception->getStatusCode(), $exception->getHeaders())->header('Cache-Control', 'no-store, private');
        });
    })->create();
