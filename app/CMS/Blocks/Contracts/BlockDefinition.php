<?php

namespace App\CMS\Blocks\Contracts;

use App\CMS\Blocks\Support\BlockTemplate;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Component;

interface BlockDefinition
{
    public function key(): string;

    public function label(): string;

    public function icon(): ?string;

    public function version(): int;

    /** @return array<string, BlockTemplate> */
    public function templates(): array;

    public function defaultTemplate(): string;

    /** @return array<string> */
    public function capabilities(): array;

    /** @return array<Component> */
    public function filamentSchema(string $context): array;

    public function filamentBlock(string $context): Block;
}
