<?php

namespace App\Services;

use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupArchiveBuilder
{
    public function __construct(
        private readonly DatabaseBackupProducer $database,
        private readonly PersistentStorageRegistry $storage,
    ) {}

    /** @return array{path:string,size:int,checksum:string,metadata:array} */
    public function build(Backup $backup): array
    {
        $directory = storage_path('app/private/'.config('backup.temporary_prefix').'/'.$backup->uuid);
        File::ensureDirectoryExists($directory, 0700, true);
        $archiveName = 'cms-'.$backup->type->value.'-'.now()->format('Y-m-d-His').'-'.substr($backup->uuid, 0, 8).'.zip';
        $archivePath = $directory.DIRECTORY_SEPARATOR.$archiveName;
        if (is_file($archivePath)) {
            @unlink($archivePath);
        }
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new BackupOperationException('archive_failed', 'فایل فشرده نسخه پشتیبان ساخته نشد.');
        }

        $fileCount = 0;
        $fileBytes = 0;
        try {
            if (in_array($backup->type, [BackupType::Full, BackupType::Database], true)) {
                $dump = $directory.DIRECTORY_SEPARATOR.'database.sql';
                $this->database->dump($dump);
                $zip->addFile($dump, 'database/database.sql');
            }
            if (in_array($backup->type, [BackupType::Full, BackupType::Files], true)) {
                foreach ($this->storage->files() as $file) {
                    $zip->addFile($file['path'], $file['archive_path']);
                    $fileCount++;
                    $fileBytes += $file['size'];
                }
            }
            $manifest = [
                'format_version' => 1,
                'manifest_version' => 1,
                'backup_uuid' => $backup->uuid,
                'type' => $backup->type->value,
                'created_at' => now()->toIso8601String(),
                'database_driver' => config('database.connections.'.config('database.default').'.driver'),
                'application' => ['name' => config('app.name'), 'laravel_version' => app()->version()],
                'files' => ['count' => $fileCount, 'bytes' => $fileBytes],
                'checksum' => ['algorithm' => 'sha256', 'scope' => 'archive'],
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } finally {
            $zip->close();
            if (isset($dump) && is_file($dump)) {
                @unlink($dump);
            }
        }

        if (! is_file($archivePath)) {
            throw new BackupOperationException('archive_failed', 'فایل فشرده نسخه پشتیبان ساخته نشد.');
        }

        return [
            'path' => $archivePath,
            'size' => filesize($archivePath),
            'checksum' => hash_file('sha256', $archivePath),
            'metadata' => $manifest,
        ];
    }
}
