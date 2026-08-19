<?php

use App\Http\Middleware\EnsureClientUser;
use App\Http\Middleware\EnsureCustomerServiceCapability;
use App\Http\Middleware\LogLivewireRequests;
use App\Http\Middleware\ResolveRedirects;
use App\Http\Middleware\ShareClientPortalContext;
use App\Support\AdminLoginPath;
use App\Support\TemporaryDebugLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn ($request): string => $request->routeIs('client.*', 'account.*')
            ? route('login')
            : app(AdminLoginPath::class)->url());
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
        // TEMP DEBUG - remove after production save issue is fixed.
        $exceptions->report(function (Throwable $exception): void {
            TemporaryDebugLogger::logException('TEMP DEBUG - global throwable reported', $exception);
        });
    })->create();
