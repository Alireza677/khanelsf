<?php

namespace App\Support;

use App\Models\BackupRestore;

readonly class BackupRestoreStart
{
    public function __construct(public BackupRestore $restore, public string $progressToken) {}

    public function progressUrl(): string
    {
        return route('backup-restore.progress', ['restore' => $this->restore->uuid, 'token' => $this->progressToken]);
    }
}
