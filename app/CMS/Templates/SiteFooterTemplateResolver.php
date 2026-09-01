<?php

namespace App\CMS\Templates;

use App\Models\Template;
use App\Services\SettingsService;
use App\Services\TemplateService;

final class SiteFooterTemplateResolver
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly TemplateService $templates,
    ) {}

    public function selected(): ?Template
    {
        $templateId = $this->settings->footerTemplateId();

        if ($templateId === null) {
            return $this->templates->findTemplateFor('site_footer');
        }

        return Template::query()
            ->published()
            ->whereKey($templateId)
            ->where('type', 'site_footer')
            ->first();
    }
}
