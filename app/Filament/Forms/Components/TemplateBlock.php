<?php

namespace App\Filament\Forms\Components;

use App\CMS\Blocks\Contracts\HasTemplateTargets;
use Filament\Forms\Components\Builder\Block;

/** Editor metadata for legacy inline blocks without registering a new renderer. */
final class TemplateBlock extends Block implements HasTemplateTargets
{
    /** @var array<string>|null */
    protected ?array $allowedTemplateTargets = null;

    /** @param array<string>|null $targets */
    public function forTemplateTargets(?array $targets): static
    {
        $this->allowedTemplateTargets = $targets;

        return $this;
    }

    public function templateTargets(): ?array
    {
        return $this->allowedTemplateTargets;
    }
}
