<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use Illuminate\Support\Facades\File;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class PersistentStorageRestoreService
{
    public function replace(BackupRestoreArtifact $artifact, string $restoreUuid): void
    {
        $swapped = [];
        try {
            foreach ($artifact->storageScopes as $disk) {
                $definition = config("backup.persistent_disks.{$disk}");
                if (! is_array($definition) || blank($definition['root'] ?? null)) {
                    throw new BackupOperationException('restore_storage_incompatible', 'محدوده فایل‌های Backup دیگر در این نصب وجود ندارد.');
                }
                $root = rtrim((string) $definition['root'], '\\/');
                $parent = dirname($root);
                File::ensureDirectoryExists($parent, 0700, true);
                $prepared = $parent.DIRECTORY_SEPARATOR.'.cms-restore-'.$restoreUuid.'-'.$disk;
                $rollback = $parent.DIRECTORY_SEPARATOR.'.cms-rollback-'.$restoreUuid.'-'.$disk;
                File::deleteDirectory($prepared);
                File::deleteDirectory($rollback);
                File::ensureDirectoryExists($prepared, 0700, true);

                $source = $artifact->stagingPath.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$disk;
                if (is_dir($source)) {
                    $this->copyTree($source, $prepared);
                }
                foreach ($definition['excludes'] ?? [] as $excluded) {
                    $relative = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, (string) $excluded), DIRECTORY_SEPARATOR);
                    if ($relative !== '' && file_exists($root.DIRECTORY_SEPARATOR.$relative)) {
                        $this->copyPath($root.DIRECTORY_SEPARATOR.$relative, $prepared.DIRECTORY_SEPARATOR.$relative);
                    }
                }

                if (is_dir($root) && ! @rename($root, $rollback)) {
                    throw new BackupOperationException('restore_storage_swap_failed', 'جابه‌جایی ایمن فضای ذخیره‌سازی ناموفق بود.');
                }
                if (! @rename($prepared, $root)) {
                    is_dir($rollback) && @rename($rollback, $root);
                    throw new BackupOperationException('restore_storage_swap_failed', 'اعمال فایل‌های نسخه پشتیبان ناموفق بود.');
                }
                $swapped[] = ['root' => $root, 'rollback' => $rollback];
            }

            foreach ($swapped as $scope) {
                File::deleteDirectory($scope['rollback']);
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($swapped) as $scope) {
                File::deleteDirectory($scope['root']);
                @rename($scope['rollback'], $scope['root']);
            }
            throw $exception instanceof BackupOperationException
                ? $exception
                : new BackupOperationException('restore_storage_failed', 'بازیابی فضای ذخیره‌سازی ناموفق بود.', $exception);
        }
    }

    private function copyPath(string $source, string $destination): void
    {
        if (is_link($source)) {
            return;
        }
        if (is_dir($source)) {
            File::ensureDirectoryExists($destination, 0700, true);
            $this->copyTree($source, $destination);
            return;
        }
        File::ensureDirectoryExists(dirname($destination), 0700, true);
        if (! @copy($source, $destination)) {
            throw new BackupOperationException('restore_storage_copy_failed', 'کپی فایل‌های فضای ذخیره‌سازی ناموفق بود.');
        }
    }

    private function copyTree(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            /** @var SplFileInfo $entry */
            if ($entry->isLink()) {
                continue;
            }
            $relative = substr($entry->getPathname(), strlen($source) + 1);
            $target = $destination.DIRECTORY_SEPARATOR.$relative;
            if ($entry->isDir()) {
                File::ensureDirectoryExists($target, 0700, true);
            } elseif (! @copy($entry->getPathname(), $target)) {
                throw new BackupOperationException('restore_storage_copy_failed', 'کپی فایل‌های فضای ذخیره‌سازی ناموفق بود.');
            }
        }
    }
}
