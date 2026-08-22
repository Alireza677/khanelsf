<?php

namespace App\Services;

use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupUploadService
{
    public function __construct(
        private readonly LocalBackupStorage $storage,
        private readonly LocalBackupRetentionService $retention,
    ) {}

    public function accept(string $incomingPath, User $user): Backup
    {
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($incomingPath);
        $backup = null;
        $stored = null;

        try {
            if (! is_file($absolutePath)) {
                throw new BackupOperationException('upload_missing', 'فایل آپلودشده در دسترس نیست.');
            }
            $size = filesize($absolutePath);
            $maximum = max(1, (int) config('backup.upload_max_mb', 2048)) * 1024 * 1024;
            if ($size === false || $size < 1 || $size > $maximum) {
                throw new BackupOperationException('upload_too_large', 'حجم فایل نسخه پشتیبان بیشتر از حد مجاز است.');
            }

            $checksum = hash_file('sha256', $absolutePath);
            if (Backup::query()->where('status', BackupStatus::Completed->value)->where('checksum', $checksum)->exists()) {
                throw new BackupOperationException('duplicate_backup', 'این نسخه پشتیبان قبلاً بارگذاری شده است.');
            }

            $manifest = $this->inspect($absolutePath);
            $type = BackupType::tryFrom((string) ($manifest['type'] ?? ''));
            if (! $type) {
                throw new BackupOperationException('invalid_backup_manifest', 'این فایل یک نسخه پشتیبان معتبر CMS نیست.');
            }

            $backup = Backup::query()->create([
                'uuid' => (string) Str::uuid(),
                'type' => $type,
                'source' => BackupSource::Uploaded,
                'status' => BackupStatus::Creating,
                'requested_by' => $user->id,
                'idempotency_key' => 'upload:'.$checksum,
                'attempt' => 1,
                'archive_name' => 'cms-'.$type->value.'-uploaded-'.now()->format('Y-m-d-His').'.zip',
                'archive_format' => 'zip',
                'archive_version' => (int) $manifest['format_version'],
                'manifest_version' => (int) $manifest['manifest_version'],
                'started_at' => now(),
                'size_bytes' => $size,
                'checksum_algorithm' => 'sha256',
                'checksum' => $checksum,
                'metadata' => ['manifest' => $manifest, 'uploaded_archive_name' => basename($incomingPath)],
            ]);

            $stored = $this->storage->store($backup, $absolutePath);
            $backup->update([
                'local_disk' => $stored['disk'],
                'local_path' => $stored['path'],
                'status' => BackupStatus::Completed,
                'finished_at' => now(),
            ]);
            $this->retention->prune();

            return $backup->fresh();
        } catch (\Throwable $exception) {
            $disk->delete($incomingPath);
            if (is_array($stored) && isset($stored['path'])) {
                Storage::disk($stored['disk'])->delete($stored['path']);
            }
            if ($backup?->exists && $backup->status !== BackupStatus::Completed) {
                $backup->update([
                    'status' => BackupStatus::Failed,
                    'failure_code' => $exception instanceof BackupOperationException ? $exception->failureCode : 'upload_failed',
                    'failure_summary' => $exception instanceof BackupOperationException ? $exception->getMessage() : 'بارگذاری نسخه پشتیبان ناموفق بود.',
                    'finished_at' => now(),
                ]);
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    private function inspect(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new BackupOperationException('invalid_zip', 'این فایل یک ZIP معتبر نیست.');
        }

        try {
            $manifestCount = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                $normalized = str_replace('\\', '/', $name);
                if ($normalized === 'manifest.json') {
                    $manifestCount++;
                }
                if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized)
                    || in_array('..', explode('/', $normalized), true) || str_contains($normalized, "\0")) {
                    throw new BackupOperationException('unsafe_archive_path', 'ساختار مسیرهای فایل نسخه پشتیبان معتبر نیست.');
                }
            }
            if ($manifestCount !== 1) {
                throw new BackupOperationException('manifest_missing', 'این فایل یک نسخه پشتیبان معتبر CMS نیست.');
            }

            $limit = max(1024, (int) config('backup.manifest_max_bytes', 1048576));
            $stat = $zip->statName('manifest.json');
            if (! is_array($stat) || ($stat['size'] ?? 0) > $limit) {
                throw new BackupOperationException('manifest_too_large', 'Manifest نسخه پشتیبان معتبر نیست.');
            }
            $json = $zip->getFromName('manifest.json', $limit + 1);
            $manifest = is_string($json) ? json_decode($json, true) : null;
            if (! is_array($manifest) || ($manifest['format_version'] ?? null) !== 1 || ($manifest['manifest_version'] ?? null) !== 1) {
                throw new BackupOperationException('unsupported_manifest_version', 'نسخه Manifest این فایل پشتیبانی نمی‌شود.');
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }
}
