<?php

namespace App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class PersistentStorageRegistry
{
    /** @return iterable<array{path:string, archive_path:string, size:int}> */
    public function files(): iterable
    {
        foreach (config('backup.persistent_disks', []) as $disk => $definition) {
            $root = realpath((string) $definition['root']);
            if ($root === false || ! is_dir($root)) {
                continue;
            }
            $excludes = array_map(fn (string $path): string => trim(str_replace('\\', '/', $path), '/'), $definition['excludes'] ?? []);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $realPath = $file->getRealPath();
                if ($realPath === false || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($realPath, strlen($root) + 1));
                if ($this->excluded($relative, $excludes)) {
                    continue;
                }
                yield [
                    'path' => $realPath,
                    'archive_path' => 'files/'.$disk.'/'.$relative,
                    'size' => $file->getSize(),
                ];
            }
        }
    }

    private function excluded(string $relative, array $excludes): bool
    {
        foreach ($excludes as $exclude) {
            if ($relative === $exclude || str_starts_with($relative, $exclude.'/')) {
                return true;
            }
        }

        return false;
    }
}
