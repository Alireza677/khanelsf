<?php

namespace App\Services;

use App\Models\BackupRestore;

class BackupRestoreProgressAccess
{
    public function issue(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'token' => $token,
            'hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds((int) config('backup.restore_timeout', 7200) + 600),
        ];
    }

    public function authorize(BackupRestore $restore, ?string $token): void
    {
        abort_unless(
            is_string($token) && strlen($token) === 64
            && filled($restore->progress_token_hash)
            && $restore->progress_token_expires_at?->isFuture()
            && hash_equals((string) $restore->progress_token_hash, hash('sha256', $token)),
            403,
        );
    }
}
