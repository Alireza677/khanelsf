<?php

namespace App\CMS\Blocks\Support;

use App\CMS\Blocks\Contracts\BlockDefinition;
use Filament\Forms\Components\Builder\Block;

abstract class AbstractBlock implements BlockDefinition
{
    public function icon(): ?string
    {
        return null;
    }

    public function version(): int
    {
        return 1;
    }

    public function capabilities(): array
    {
        return [];
    }

    public function filamentBlock(string $context): Block
    {
        $block = Block::make($this->key())
            ->label($this->label())
            ->schema($this->filamentSchema($context));

        if ($icon = $this->icon()) {
            $block->icon($icon);
        }

        return $block;
    }
}
