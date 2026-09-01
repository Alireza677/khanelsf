<?php

namespace App\Filament\Resources\Concerns;

trait HasStickyFormActions
{
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'fi-always-sticky-form-actions',
        ];
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }
}
