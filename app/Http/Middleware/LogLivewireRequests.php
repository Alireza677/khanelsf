<?php

namespace App\Http\Middleware;

use App\Support\TemporaryDebugLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogLivewireRequests
{
    // TEMP DEBUG - remove after production save issue is fixed.
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isLivewireUpdate($request)) {
            return $next($request);
        }

        $startedAt = microtime(true);

        Log::debug('TEMP DEBUG - Livewire update request started', [
            ...TemporaryDebugLogger::requestContext($request),
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
        ]);

        try {
            $response = $next($request);

            Log::debug('TEMP DEBUG - Livewire update request finished', [
                ...TemporaryDebugLogger::requestContext($request),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'memory_usage' => memory_get_usage(true),
                'peak_memory_usage' => memory_get_peak_usage(true),
            ]);

            return $response;
        } catch (Throwable $exception) {
            TemporaryDebugLogger::logException('TEMP DEBUG - Livewire update request failed', $exception, $request, [
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'memory_usage' => memory_get_usage(true),
                'peak_memory_usage' => memory_get_peak_usage(true),
            ]);

            throw $exception;
        }
    }

    private function isLivewireUpdate(Request $request): bool
    {
        return $request->is('livewire/update') || str_contains($request->path(), 'livewire/update');
    }
}
