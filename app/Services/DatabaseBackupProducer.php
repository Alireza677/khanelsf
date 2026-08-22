<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupProducer
{
    public function dump(string $destination): void
    {
        $connectionName = (string) config('database.default');
        $database = config("database.connections.{$connectionName}");
        if (($database['driver'] ?? null) !== 'mysql') {
            throw new BackupOperationException('database_driver_unsupported', 'در این نسخه فقط پشتیبان‌گیری MySQL پشتیبانی می‌شود.');
        }

        $credentials = tempnam(dirname($destination), 'mysql-');
        if ($credentials === false) {
            throw new BackupOperationException('database_dump_failed', 'فایل امن تنظیمات پایگاه داده ساخته نشد.');
        }

        try {
            file_put_contents($credentials, implode(PHP_EOL, [
                '[client]',
                'user='.$this->option((string) ($database['username'] ?? '')),
                'password='.$this->option((string) ($database['password'] ?? '')),
                'host='.$this->option((string) ($database['host'] ?? '127.0.0.1')),
                'port='.(int) ($database['port'] ?? 3306),
                'default-character-set='.(string) ($database['charset'] ?? 'utf8mb4'),
            ]).PHP_EOL, LOCK_EX);
            @chmod($credentials, 0600);

            $process = new Process([
                (string) config('backup.database_dump_binary', 'mysqldump'),
                '--defaults-extra-file='.$credentials,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--hex-blob',
                '--result-file='.$destination,
                (string) $database['database'],
            ]);
            $process->setTimeout((int) config('backup.timeout', 3600));
            $process->run();
            if (! $process->isSuccessful() || ! is_file($destination)) {
                $code = $process->getExitCode() === 127 ? 'dump_binary_missing' : 'database_dump_failed';
                throw new BackupOperationException($code, 'تهیه خروجی پایگاه داده ناموفق بود.');
            }
        } catch (BackupOperationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new BackupOperationException('database_dump_failed', 'تهیه خروجی پایگاه داده ناموفق بود.', $exception);
        } finally {
            if (is_file($credentials)) {
                @unlink($credentials);
            }
        }
    }

    private function option(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"';
    }
}
