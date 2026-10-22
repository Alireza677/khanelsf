<?php

namespace App\Services;

final class VideoLibrarySettings
{
    public const DEFAULT_TITLE = 'کتابخانه ویدئویی';

    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return filter_var($this->settings->get('video_library_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function title(): string
    {
        return (string) ($this->settings->get('video_library_archive_title') ?: self::DEFAULT_TITLE);
    }

    public function description(): ?string
    {
        return $this->settings->get('video_library_archive_description');
    }

    public function perPage(): int
    {
        return max(1, min(48, (int) $this->settings->get('video_library_per_page', 12)));
    }
}
