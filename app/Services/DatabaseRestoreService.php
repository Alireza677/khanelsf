<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DatabaseRestoreService
{
    public function __construct(private readonly DatabaseRestoreDumpFilter $filter) {}

    public function restore(string $dumpPath, ?string $restoreUuid = null): void
    {
        $connectionName = (string) config('database.default');
        $database = config("database.connections.{$connectionName}");
        if (($database['driver'] ?? null) !== 'mysql') {
            throw new BackupOperationException('restore_database_driver_unsupported', 'بازیابی فقط برای پایگاه داده MySQL پشتیبانی می‌شود.');
        }
        if (! is_file($dumpPath) || filesize($dumpPath) < 1) {
            throw new BackupOperationException('restore_database_missing', 'فایل dump پایگاه داده در دسترس نیست.');
        }

        $credentials = tempnam(dirname($dumpPath), 'mysql-restore-');
        $filteredDump = tempnam(dirname($dumpPath), 'mysql-filtered-');
        if ($credentials === false || $filteredDump === false) {
            is_string($credentials) && @unlink($credentials);
            is_string($filteredDump) && @unlink($filteredDump);
            throw new BackupOperationException('restore_database_failed', 'فایل امن اتصال پایگاه داده ساخته نشد.');
        }
        try {
            $representedTables = $this->filter->filter($dumpPath, $filteredDump);
            file_put_contents($credentials, implode(PHP_EOL, [
                '[client]',
                'user='.$this->option((string) ($database['username'] ?? '')),
                'password='.$this->option((string) ($database['password'] ?? '')),
                'host='.$this->option((string) ($database['host'] ?? '127.0.0.1')),
                'port='.(int) ($database['port'] ?? 3306),
                'default-character-set='.(string) ($database['charset'] ?? 'utf8mb4'),
            ]).PHP_EOL, LOCK_EX);
            @chmod($credentials, 0600);

            $this->dropCurrentOnlyApplicationTables($connectionName, (string) $database['database'], $representedTables);
            DB::disconnect($connectionName);
            $input = fopen($filteredDump, 'rb');
            $process = new Process([
                (string) config('backup.database_restore_binary', 'mysql'),
                '--defaults-extra-file='.$credentials,
                '--binary-mode=1',
                '--database='.(string) $database['database'],
            ]);
            $process->setInput($input);
            $process->setTimeout((int) config('backup.restore_timeout', 7200));
            $process->run();
            is_resource($input) && fclose($input);
            DB::purge($connectionName);

            if (! $process->isSuccessful()) {
                Log::error('CMS database restore process failed.', [
                    'restore_uuid' => $restoreUuid,
                    'step' => 'restoring_database',
                    'exit_code' => $process->getExitCode(),
                    'stderr' => $this->sanitizeDiagnostics($process->getErrorOutput(), $credentials),
                ]);
                throw new BackupOperationException('restore_database_failed', 'ورود dump پایگاه داده ناموفق بود.');
            }
        } catch (BackupOperationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new BackupOperationException('restore_database_failed', 'بازیابی پایگاه داده ناموفق بود.', $exception);
        } finally {
            DB::purge($connectionName);
            if (is_file($credentials)) {
                @unlink($credentials);
            }
            if (is_file($filteredDump)) {
                @unlink($filteredDump);
            }
        }
    }

    /** @param list<string> $representedTables */
    private function dropCurrentOnlyApplicationTables(string $connection, string $database, array $representedTables): void
    {
        $operational = array_fill_keys(config('backup.operational_tables', []), true);
        $represented = array_fill_keys($representedTables, true);
        $rows = DB::connection($connection)->select(
            'select table_name from information_schema.tables where table_schema = ? and table_type = ?',
            [$database, 'BASE TABLE'],
        );
        $extras = [];
        foreach ($rows as $row) {
            $table = (string) ($row->table_name ?? $row->TABLE_NAME ?? '');
            if ($table !== '' && ! isset($operational[$table]) && ! isset($represented[$table])) {
                $extras[] = $table;
            }
        }
        if ($extras === []) {
            return;
        }

        DB::connection($connection)->unprepared('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($extras as $table) {
                DB::connection($connection)->statement('DROP TABLE `'.str_replace('`', '``', $table).'`');
            }
        } finally {
            DB::connection($connection)->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function option(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"';
    }

    private function sanitizeDiagnostics(string $stderr, string $credentials): string
    {
        $sanitized = str_replace($credentials, '[credential-file]', $stderr);
        $sanitized = preg_replace('/(?i)(password|passwd|pwd)\s*[=:]\s*[^\s]+/', '$1=[redacted]', $sanitized) ?? '';
        $sanitized = preg_replace('/(?i)(mysql:\/\/[^:\s]+:)[^@\s]+@/', '$1[redacted]@', $sanitized) ?? '';

        return mb_substr(trim($sanitized), 0, 2000);
    }
}
