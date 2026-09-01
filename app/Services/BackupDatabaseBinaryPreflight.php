<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;

class BackupDatabaseBinaryPreflight
{
    public function assertAvailable(string $restoreUuid): void
    {
        foreach (['database_dump_binary', 'database_restore_binary'] as $key) {
            $binary = (string) config('backup.'.$key);
            if ($this->resolve($binary) === null) {
                Log::error('Backup restore binary preflight failed.', [
                    'restore_uuid' => $restoreUuid,
                    'step' => 'preflight',
                    'binary_config' => $key,
                    'configured_path' => $binary,
                ]);
                throw new BackupOperationException(
                    'restore_binary_missing',
                    'ابزار موردنیاز برای بازیابی پایگاه داده روی سرور در دسترس نیست.',
                );
            }
        }
    }

    private function resolve(string $binary): ?string
    {
        if ($binary === '') {
            return null;
        }
        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return is_file($binary) && (DIRECTORY_SEPARATOR === '\\' || is_executable($binary)) ? $binary : null;
        }

        return (new ExecutableFinder)->find($binary);
    }
}
