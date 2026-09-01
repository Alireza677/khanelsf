<?php

namespace App\Services;

use App\Enums\BackupRestoreStatus;
use Illuminate\Support\Facades\Storage;

class BackupRestoreStateStore
{
    public function read(string $uuid): array
    {
        $path = $this->path($uuid);
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return [];
        }
        $state = json_decode($disk->get($path), true);

        return is_array($state) ? $state : [];
    }

    public function write(string $uuid, BackupRestoreStatus $status, array $state = []): void
    {
        $path = $this->path($uuid);
        $temporary = $path.'.tmp';
        $payload = json_encode([
            ...$this->read($uuid),
            'restore_uuid' => $uuid,
            'status' => $status->value,
            'current_step' => $status->value,
            'updated_at' => now()->toIso8601String(),
            ...$state,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $disk = Storage::disk('local');
        $disk->put($temporary, $payload);
        $disk->move($temporary, $path);
    }

    private function path(string $uuid): string
    {
        return trim((string) config('backup.restore_state_prefix', 'backups/restores'), '/').'/'.$uuid.'.json';
    }
}
