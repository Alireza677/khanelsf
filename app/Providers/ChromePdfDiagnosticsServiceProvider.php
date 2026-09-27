<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Spatie\LaravelPdf\Drivers\ChromeDriver;
use Stringable;

final class ChromePdfDiagnosticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->environment('local') || ! config('app.debug')) {
            return;
        }

        // Spatie 2.13 does not expose Chrome PHP's official debugLogger option.
        // Retain all renderer behavior and add only a startup logger locally.
        $this->app->singleton('laravel-pdf.driver.chrome', fn () => new class(config('laravel-pdf.chrome', [])) extends ChromeDriver
        {
            protected function buildBrowserOptions(): array
            {
                $options = parent::buildBrowserOptions();
                $options['debugLogger'] = new class(Log::build([
                    'driver' => 'single',
                    'path' => storage_path('logs/chrome-pdf-debug.log'),
                    'level' => 'debug',
                ])) extends AbstractLogger
                {
                    public function __construct(private readonly LoggerInterface $logger) {}

                    public function log($level, string|Stringable $message, array $context = []): void
                    {
                        $message = (string) $message;

                        // Raw startup stderr and the actual command only. Never log
                        // DevTools protocol messages (which include report HTML),
                        // WebSocket connection messages, or arbitrary log context.
                        foreach (['process: initializing', 'process: using directory:', 'process: starting process:', 'process: waiting for ', 'process: chrome output:'] as $prefix) {
                            if (str_starts_with($message, $prefix)) {
                                $this->logger->log($level, $message);

                                return;
                            }
                        }
                    }
                };

                return $options;
            }
        });
    }
}
