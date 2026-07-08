<?php

namespace App\CMS\Blocks;

use App\CMS\Blocks\Contracts\BlockDefinition;
use Filament\Forms\Components\Builder\Block;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class BlockRegistry
{
    /**
     * @param  array<string, class-string<BlockDefinition>>  $definitionsByKey
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $definitionsByKey,
    ) {}

    public function find(string $key): BlockDefinition
    {
        $class = $this->definitionsByKey[$key] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Block [{$key}] is not registered.");
        }

        $definition = $this->container->make($class);

        if ($definition->key() !== $key) {
            throw new InvalidArgumentException("Registered block key [{$key}] does not match definition key [{$definition->key()}].");
        }

        return $definition;
    }

    /** @return array<string> */
    public function keys(): array
    {
        return array_keys($this->definitionsByKey);
    }

    /**
     * Build only the explicitly requested definitions. This keeps heavy Filament
     * schemas lazy and allows callers to filter blocks first.
     *
     * @param  array<string>  $keys
     * @return array<Block>
     */
    public function filamentBlocks(array $keys, string $context): array
    {
        $blocks = [];

        foreach ($keys as $key) {
            $blocks[] = $this->find($key)->filamentBlock($context);
        }

        return $blocks;
    }
}
