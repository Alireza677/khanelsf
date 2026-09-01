<?php

namespace App\Support;

final class BackupUploadLimit
{
    public static function megabytes(): int
    {
        return max(1, (int) config('backup.upload_max_mb', 512));
    }

    public static function kilobytes(): int
    {
        return self::megabytes() * 1024;
    }

    public static function bytes(): int
    {
        return self::kilobytes() * 1024;
    }

    public static function validationMessage(): string
    {
        return 'حداکثر حجم مجاز فایل نسخه پشتیبان '.PersianDate::digits(self::megabytes()).' مگابایت است.';
    }

    public static function serverKilobytes(): ?int
    {
        $limits = array_values(array_filter([
            self::iniKilobytes('upload_max_filesize'),
            self::iniKilobytes('post_max_size'),
        ], fn (int $limit): bool => $limit > 0));

        return $limits === [] ? null : min($limits);
    }

    private static function iniKilobytes(string $key): int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (float) $value;

        return (int) floor($number * match (strtolower(substr($value, -1))) {
            'g' => 1024 * 1024,
            'm' => 1024,
            'k' => 1,
            default => 1 / 1024,
        });
    }
}
