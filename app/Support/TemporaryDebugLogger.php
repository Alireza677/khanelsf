<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TemporaryDebugLogger
{
    private const SENSITIVE_KEY_PARTS = [
        'password',
        'passwd',
        'token',
        '_token',
        'csrf',
        'cookie',
        'authorization',
        'secret',
        'api_key',
        'apikey',
        'app_key',
        'db_password',
        'session',
    ];

    private const LARGE_FIELD_PARTS = [
        'content',
        'body',
        'description',
        'excerpt',
        'message',
        'schema',
        'blocks',
        'builder',
        'conditions',
        'value',
        'note',
    ];

    public static function filamentSaveStarted(string $action, string $component, ?string $modelClass, ?Model $record, array $data): void
    {
        Log::debug('TEMP DEBUG - Filament save started', [
            ...self::requestContext(),
            'action' => $action,
            'component' => $component,
            'model_class' => $modelClass,
            'model_id' => $record?->getKey(),
            'payload' => self::payloadSummary($data),
            'large_field_lengths' => self::largeFieldLengths($data),
            'save_started' => true,
        ]);
    }

    public static function filamentSaveCompleted(string $action, string $component, ?string $modelClass, ?Model $record, array $data): void
    {
        Log::debug('TEMP DEBUG - Filament save completed', [
            ...self::requestContext(),
            'action' => $action,
            'component' => $component,
            'model_class' => $modelClass,
            'model_id' => $record?->getKey(),
            'payload' => self::payloadSummary($data),
            'large_field_lengths' => self::largeFieldLengths($data),
            'save_completed' => true,
        ]);
    }

    public static function filamentSaveFailed(string $action, string $component, ?string $modelClass, ?Model $record, array $data, Throwable $exception): void
    {
        self::logException('TEMP DEBUG - Filament save failed', $exception, null, [
            'action' => $action,
            'component' => $component,
            'model_class' => $modelClass,
            'model_id' => $record?->getKey(),
            'payload' => self::payloadSummary($data),
            'large_field_lengths' => self::largeFieldLengths($data),
            'save_failed' => true,
        ]);
    }

    public static function settingsSaveStarted(array $data): void
    {
        Log::debug('TEMP DEBUG - Filament settings save started', [
            ...self::requestContext(),
            'action' => 'settings-save',
            'model_class' => 'App\\Models\\Setting',
            'payload' => self::payloadSummary($data),
            'large_field_lengths' => self::largeFieldLengths($data),
            'save_started' => true,
        ]);
    }

    public static function settingsSaveCompleted(array $data): void
    {
        Log::debug('TEMP DEBUG - Filament settings save completed', [
            ...self::requestContext(),
            'action' => 'settings-save',
            'model_class' => 'App\\Models\\Setting',
            'payload' => self::payloadSummary($data),
            'large_field_lengths' => self::largeFieldLengths($data),
            'save_completed' => true,
        ]);
    }

    public static function logException(string $message, Throwable $exception, ?Request $request = null, array $extra = []): void
    {
        Log::error($message, [
            ...self::requestContext($request),
            ...$extra,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'database_exception_message' => $exception instanceof \Illuminate\Database\QueryException
                ? $exception->getMessage()
                : null,
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'trace_summary' => self::traceSummary($exception),
            'validation_errors' => $exception instanceof ValidationException
                ? self::validationErrorSummary($exception)
                : null,
            'csrf_token_mismatch' => $exception instanceof \Illuminate\Session\TokenMismatchException,
        ]);
    }

    public static function requestContext(?Request $request = null): array
    {
        $request ??= request();

        return [
            'user_id' => Auth::id(),
            'route_name' => $request->route()?->getName(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'path' => $request->path(),
            'is_livewire_update' => $request->is('livewire/update') || Str::contains($request->path(), 'livewire/update'),
            'is_ajax' => $request->ajax(),
            'has_livewire_headers' => $request->headers->has('X-Livewire') || $request->headers->has('X-Livewire-Navigate'),
            'content_length' => $request->server('CONTENT_LENGTH'),
            'session_id_available' => $request->hasSession() && filled($request->session()->getId()),
            'input_keys' => self::safeKeys($request->input()),
            'large_input_lengths' => self::largeFieldLengths($request->input()),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ];
    }

    public static function payloadSummary(array $data): array
    {
        return [
            'top_level_keys' => self::safeKeys($data),
            'key_count' => count($data),
        ];
    }

    public static function largeFieldLengths(array $data): array
    {
        $lengths = [];

        self::walkValues($data, function (string $key, mixed $value) use (&$lengths): void {
            if (! self::isLargeField($key) || self::isSensitiveKey($key)) {
                return;
            }

            $serialized = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            $lengths[$key] = [
                'type' => get_debug_type($value),
                'length' => strlen((string) $serialized),
                'mb_length' => mb_strlen((string) $serialized),
            ];
        });

        return $lengths;
    }

    public static function validationErrorSummary(ValidationException $exception): array
    {
        return collect($exception->errors())
            ->map(fn (array $messages): int => count($messages))
            ->all();
    }

    public static function traceSummary(Throwable $exception, int $limit = 8): array
    {
        return collect($exception->getTrace())
            ->take($limit)
            ->map(fn (array $frame): array => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ])
            ->all();
    }

    private static function safeKeys(array $data): array
    {
        return collect(array_keys($data))
            ->map(fn (string|int $key): string => (string) $key)
            ->reject(fn (string $key): bool => self::isSensitiveKey($key))
            ->values()
            ->all();
    }

    private static function walkValues(array $data, callable $callback, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            $callback($path, $value);

            if (is_array($value)) {
                self::walkValues($value, $callback, $path);
            }
        }
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = Str::lower($key);

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (Str::contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    private static function isLargeField(string $key): bool
    {
        $key = Str::lower($key);

        foreach (self::LARGE_FIELD_PARTS as $part) {
            if (Str::contains($key, $part)) {
                return true;
            }
        }

        return false;
    }
}
