<?php

namespace App\CMS\Templates;

use App\Models\Template;
use App\Services\SettingsService;

final class SiteHeaderTemplateResolver
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function selected(): ?Template
    {
        $templateId = $this->settings->headerTemplateId();

        if ($templateId === null) {
            return null;
        }

        return Template::query()
            ->published()
            ->whereKey($templateId)
            ->where('type', 'site_header')
            ->first();
    }
}
