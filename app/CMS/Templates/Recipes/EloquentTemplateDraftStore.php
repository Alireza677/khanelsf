<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Templates\Recipes\Contracts\TemplateDraftStore;
use App\Models\Template;
use Illuminate\Support\Str;

final class EloquentTemplateDraftStore implements TemplateDraftStore
{
    public function persist(Template $template): Template
    {
        $template->slug = $this->uniqueSlug($template->slug);
        $template->save();

        return $template;
    }

    private function uniqueSlug(string $requestedSlug): string
    {
        $base = Str::slug($requestedSlug) ?: 'template';
        $slug = $base;
        $suffix = 2;

        while (Template::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
