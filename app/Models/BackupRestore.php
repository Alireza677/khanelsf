<?php

namespace App\Models;

use App\Enums\BackupRestoreStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BackupRestore extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $restore): void {
            $restore->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BackupRestoreStatus::class,
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'progress_token_expires_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(Backup::class, 'safety_backup_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
