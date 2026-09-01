<?php

namespace App\Services;

final readonly class BackupRestoreArtifact
{
    public function __construct(
        public string $stagingPath,
        public string $databaseDumpPath,
        public array $storageScopes,
        public array $manifest,
    ) {}
}
