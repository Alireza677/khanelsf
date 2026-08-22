<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class LocalBackupStorage
{
    /** @return array{disk:string,path:string,absolute_path:string} */
    public function store(Backup $backup, string $temporaryPath): array
    {
        $disk = Storage::disk('local');
        $relativePath = trim((string) config('backup.files_prefix', 'backups/files'), '/').'/'.$backup->uuid.'.zip';
        $directory = dirname($disk->path($relativePath));
        File::ensureDirectoryExists($directory, 0700, true);

        if (! @rename($temporaryPath, $disk->path($relativePath))) {
            if (! @copy($temporaryPath, $disk->path($relativePath)) || ! @unlink($temporaryPath)) {
                throw new BackupOperationException('backup_storage_failed', 'ذخیره نسخه پشتیبان در فضای خصوصی ناموفق بود.');
            }
        }

        return ['disk' => 'local', 'path' => $relativePath, 'absolute_path' => $disk->path($relativePath)];
    }
}
