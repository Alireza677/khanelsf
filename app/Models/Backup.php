<?php

namespace App\Models;

use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Backup extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $backup): void {
            $backup->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => BackupType::class,
            'source' => BackupSource::class,
            'status' => BackupStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'size_bytes' => 'integer',
            'attempt' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isAvailable(): bool
    {
        return $this->status === BackupStatus::Completed
            && filled($this->local_disk)
            && filled($this->local_path);
    }

    public function safeFailureMessage(): string
    {
        return match ($this->failure_code) {
            'dump_binary_missing' => 'ابزار تهیه نسخه پایگاه داده در دسترس نیست.',
            'database_dump_failed' => 'تهیه نسخه پایگاه داده ناموفق بود.',
            'archive_failed' => 'ساخت فایل نسخه پشتیبان ناموفق بود.',
            'local_disk_full', 'backup_storage_failed' => 'فضای کافی برای ایجاد نسخه پشتیبان وجود ندارد.',
            'backup_file_missing' => 'فایل نسخه پشتیبان در دسترس نیست.',
            default => 'در ایجاد نسخه پشتیبان مشکلی رخ داد.',
        };
    }
}
