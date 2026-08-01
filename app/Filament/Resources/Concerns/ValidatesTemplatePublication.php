<?php

namespace App\Filament\Resources\Concerns;

use App\CMS\Templates\TemplatePublicationValidator;
use Illuminate\Validation\ValidationException;

trait ValidatesTemplatePublication
{
    protected function validateTemplatePublication(array $data): array
    {
        if (($data['status'] ?? null) !== 'published'
            || ($data['type'] ?? null) !== 'service_single') {
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
