<?php

namespace App\Support;

use App\Services\SettingsService;

final class FormUpload
{
    public const MODE_ALL = 'all';
    public const MODE_IMAGE = 'image';
    public const MODE_DOCUMENT = 'document';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    public static function mode(mixed $value): string
    {
        return in_array($value, [self::MODE_IMAGE, self::MODE_DOCUMENT], true)
            ? $value
            : self::MODE_ALL;
    }

    public static function extensions(string $mode): array
    {
        return match (self::mode($mode)) {
            self::MODE_IMAGE => self::IMAGE_EXTENSIONS,
            self::MODE_DOCUMENT => self::DOCUMENT_EXTENSIONS,
            default => [...self::IMAGE_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS],
        };
    }

    public static function maxSizeMb(?SettingsService $settings = null): int
    {
        $value = ($settings ?? app(SettingsService::class))->get('form_max_upload_size_mb', 10);

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: 10;
    }

    public static function extensionLabel(string $mode): string
    {
        return collect(self::extensions($mode))->map(fn (string $extension): string => strtoupper($extension))->implode('، ');
    }

    public static function mimeTypes(string $mode): array
    {
        $images = ['image/jpeg', 'image/png', 'image/webp'];
        $documents = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];

        return match (self::mode($mode)) {
            self::MODE_IMAGE => $images,
            self::MODE_DOCUMENT => $documents,
            default => [...$images, ...$documents],
        };
    }
}
