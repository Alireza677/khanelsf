<?php

namespace App\Filament\Resources\Concerns;

use App\CMS\Templates\TemplatePublicationValidator;
use Illuminate\Validation\ValidationException;

trait ValidatesTemplatePublication
{
    protected function validateTemplatePublication(array $data): array
    {
        if (($data['status'] ?? null) !== 'published') {
            return $data;
        }

        if (($data['type'] ?? null) === 'project_discovery_index') {
            $hasGrid = collect($data['blocks'] ?? [])->contains(fn (mixed $block): bool => is_array($block)
                && ($block['type'] ?? null) === 'project_discovery_grid');

            if (! $hasGrid) {
                throw ValidationException::withMessages([
                    'data.blocks' => 'قالب گالری پروژه‌ها برای انتشار باید شامل بلاک «گالری پروژه‌ها» باشد.',
                ]);
            }

            return $data;
        }

        if (! in_array($data['type'] ?? null, ['service_single', 'service_index', 'blog_index', 'projects_index'], true)) {
            return $data;
        }

        $errors = app(TemplatePublicationValidator::class)->validate($data);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'data.blocks' => implode(' ', $errors),
            ]);
        }

        return $data;
    }
}
