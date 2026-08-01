<?php

namespace App\Filament\Resources\Concerns;

use App\CMS\Blocks\Support\PageHeadingAudit;
use Filament\Notifications\Notification;
use Throwable;

trait WarnsAboutMultiplePageHeadings
{
    protected function afterValidate(): void
    {
        try {
            $blocks = data_get($this->data ?? [], 'blocks', []);
            $headings = app(PageHeadingAudit::class)->h1Blocks(is_array($blocks) ? $blocks : []);

            if (count($headings) <= 1) {
                return;
            }

            Notification::make()
                ->warning()
                ->title('هشدار ساختار سئو')
                ->body('در این برگه '.count($headings).' عنوان H1 تنظیم شده است. برای ساختار بهتر سئو، فقط یک عنوان را روی H1 نگه دارید.')
                ->persistent()
                ->send();
        } catch (Throwable) {
            // SEO diagnostics are advisory and must never interrupt persistence.
        }
    }
}
