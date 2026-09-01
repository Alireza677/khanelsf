<?php

namespace App\Services;

use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupRestoreArchive
{
    public function stage(Backup $backup, string $restoreUuid): BackupRestoreArtifact
    {
        if (! $backup->isAvailable() || $backup->type !== BackupType::Full) {
            throw new BackupOperationException('restore_backup_unavailable', 'این نسخه پشتیبان برای بازیابی کامل قابل استفاده نیست.');
        }

        $disk = Storage::disk($backup->local_disk);
        if (! $disk->exists($backup->local_path)) {
            throw new BackupOperationException('backup_file_missing', 'فایل نسخه پشتیبان در دسترس نیست.');
        }
        $archivePath = $disk->path($backup->local_path);
        if (! is_string($backup->checksum) || ! hash_equals($backup->checksum, (string) hash_file('sha256', $archivePath))) {
            throw new BackupOperationException('restore_checksum_mismatch', 'Checksum نسخه پشتیبان معتبر نیست.');
        }

        $staging = storage_path('app/private/'.config('backup.temporary_prefix', 'backups/tmp').'/restore-'.$restoreUuid);
        File::deleteDirectory($staging);
        File::ensureDirectoryExists($staging, 0700, true);
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            File::deleteDirectory($staging);
            throw new BackupOperationException('invalid_zip', 'این فایل یک ZIP معتبر نیست.');
        }

        try {
            [$entries, $manifest] = $this->validateEntries($zip);
            $this->validateCompatibility($manifest, $entries);
            $free = @disk_free_space($staging);
            $required = array_sum(array_column($entries, 'size'));
            if (is_float($free) && $free < $required * 1.1) {
                throw new BackupOperationException('restore_disk_full', 'فضای کافی برای آماده‌سازی نسخه پشتیبان وجود ندارد.');
            }

            foreach ($entries as $entry) {
                if ($entry['directory'] || $entry['name'] === 'manifest.json') {
                    continue;
                }
                $destination = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry['name']);
                File::ensureDirectoryExists(dirname($destination), 0700, true);
                $input = $zip->getStream($entry['name']);
                $output = @fopen($destination, 'wb');
                if (! is_resource($input) || ! is_resource($output)) {
                    is_resource($input) && fclose($input);
                    is_resource($output) && fclose($output);
                    throw new BackupOperationException('restore_extraction_failed', 'آماده‌سازی فایل‌های نسخه پشتیبان ناموفق بود.');
                }
                $written = stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
                if ($written !== $entry['size']) {
                    throw new BackupOperationException('restore_extraction_failed', 'آماده‌سازی فایل‌های نسخه پشتیبان ناقص بود.');
                }
            }

            $scopes = is_array($manifest['storage_scopes'] ?? null)
                ? array_values($manifest['storage_scopes'])
                : array_values(array_keys(config('backup.persistent_disks', [])));

            return new BackupRestoreArtifact(
                $staging,
                $staging.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sql',
                $scopes,
                $manifest,
            );
        } catch (\Throwable $exception) {
            File::deleteDirectory($staging);
            throw $exception;
        } finally {
            $zip->close();
        }
    }

    private function validateEntries(ZipArchive $zip): array
    {
        $maximumEntries = max(1, (int) config('backup.restore_max_entries', 200000));
        if ($zip->numFiles < 1 || $zip->numFiles > $maximumEntries) {
            throw new BackupOperationException('restore_archive_entries_invalid', 'تعداد فایل‌های نسخه پشتیبان خارج از محدوده مجاز است.');
        }

        $entries = [];
        $seen = [];
        $total = 0;
        $manifestCount = 0;
        $databaseCount = 0;
        $knownScopes = array_fill_keys(array_keys(config('backup.persistent_disks', [])), true);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = is_array($stat) ? str_replace('\\', '/', (string) ($stat['name'] ?? '')) : '';
            $this->assertSafePath($name);
            $folded = strtolower($name);
            if (isset($seen[$folded])) {
                throw new BackupOperationException('restore_duplicate_path', 'مسیر تکراری در نسخه پشتیبان وجود دارد.');
            }
            $seen[$folded] = true;

            $attributes = 0;
            $operations = 0;
            if ($zip->getExternalAttributesIndex($index, $operations, $attributes)
                && (($attributes >> 16) & 0170000) === 0120000) {
                throw new BackupOperationException('restore_symlink_rejected', 'پیوند نمادین داخل نسخه پشتیبان مجاز نیست.');
            }

            $directory = str_ends_with($name, '/');
            $size = (int) ($stat['size'] ?? 0);
            $compressed = (int) ($stat['comp_size'] ?? 0);
            $total += $size;
            if ($total > (int) config('backup.restore_max_uncompressed_bytes', 21474836480)) {
                throw new BackupOperationException('restore_archive_too_large', 'حجم استخراج‌شده نسخه پشتیبان خارج از محدوده مجاز است.');
            }
            if ($size > 1048576 && $compressed > 0 && ($size / $compressed) > (int) config('backup.restore_max_compression_ratio', 200)) {
                throw new BackupOperationException('restore_archive_bomb', 'نسبت فشرده‌سازی نسخه پشتیبان غیرعادی است.');
            }

            if ($name === 'manifest.json') {
                $manifestCount++;
                if ($size > max(1024, (int) config('backup.manifest_max_bytes', 1048576))) {
                    throw new BackupOperationException('invalid_backup_manifest', 'Manifest نسخه پشتیبان بیش از حد مجاز بزرگ است.');
                }
            } elseif ($name === 'database/database.sql') {
                $databaseCount++;
            } elseif (! $directory) {
                if (preg_match('#^files/([^/]+)/.+$#', $name, $matches) !== 1 || ! isset($knownScopes[$matches[1]])) {
                    throw new BackupOperationException('restore_archive_structure_invalid', 'ساختار نسخه پشتیبان با قرارداد CMS سازگار نیست.');
                }
            }
            $entries[] = ['name' => $name, 'size' => $size, 'directory' => $directory];
        }

        if ($manifestCount !== 1 || $databaseCount !== 1) {
            throw new BackupOperationException('restore_archive_structure_invalid', 'Manifest یا dump پایگاه داده نسخه پشتیبان کامل نیست.');
        }
        $limit = max(1024, (int) config('backup.manifest_max_bytes', 1048576));
        $manifestJson = $zip->getFromName('manifest.json', $limit + 1);
        $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
        if (! is_array($manifest)) {
            throw new BackupOperationException('invalid_backup_manifest', 'Manifest نسخه پشتیبان معتبر نیست.');
        }

        return [$entries, $manifest];
    }

    private function validateCompatibility(array $manifest, array $entries): void
    {
        if (($manifest['format_version'] ?? null) !== 1 || ($manifest['manifest_version'] ?? null) !== 1
            || ($manifest['type'] ?? null) !== BackupType::Full->value) {
            throw new BackupOperationException('restore_backup_incompatible', 'نسخه یا نوع Backup برای بازیابی پشتیبانی نمی‌شود.');
        }
        if (($manifest['database_driver'] ?? null) !== 'mysql' || config('database.default') !== 'mysql') {
            throw new BackupOperationException('restore_database_incompatible', 'درایور پایگاه داده نسخه پشتیبان با این نصب سازگار نیست.');
        }
        $identifier = data_get($manifest, 'application.cms_identifier');
        if ($identifier !== null && $identifier !== 'noor/starter-cms') {
            throw new BackupOperationException('restore_cms_incompatible', 'این Backup متعلق به CMS سازگار نیست.');
        }
        $laravelVersion = data_get($manifest, 'application.laravel_version');
        if (is_string($laravelVersion) && explode('.', $laravelVersion)[0] !== explode('.', app()->version())[0]) {
            throw new BackupOperationException('restore_cms_incompatible', 'نسخه اصلی CMS با این Backup سازگار نیست.');
        }

        $configured = array_keys(config('backup.persistent_disks', []));
        $scopes = $manifest['storage_scopes'] ?? $configured;
        if (! is_array($scopes) || array_diff($scopes, $configured) !== [] || array_diff($configured, $scopes) !== []) {
            throw new BackupOperationException('restore_storage_incompatible', 'محدوده فایل‌های نسخه پشتیبان با این نصب سازگار نیست.');
        }
        if (! collect($entries)->contains(fn (array $entry): bool => $entry['name'] === 'database/database.sql' && $entry['size'] > 0)) {
            throw new BackupOperationException('restore_database_missing', 'dump پایگاه داده در نسخه پشتیبان خالی یا مفقود است.');
        }
    }

    private function assertSafePath(string $name): void
    {
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:\//', $name) === 1 || in_array('..', explode('/', $name), true)) {
            throw new BackupOperationException('unsafe_archive_path', 'ساختار مسیرهای فایل نسخه پشتیبان معتبر نیست.');
        }
    }
}
