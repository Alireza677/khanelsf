<?php

namespace App\CMS\Blocks;

use App\CMS\Blocks\Contracts\BlockDefinition;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Contracts\HasTemplateTargets;
use App\Models\Template;
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

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitionsByKey);
    }

    public function renderView(string $key, array $data = []): ?string
    {
        return $this->has($key)
            ? $this->find($key)->renderView($data)
            : null;
    }

    /** @return array<string> */
    public function keys(): array
    {
        return array_keys($this->definitionsByKey);
    }

    /** Editor-only eligibility. Existing blocks must not be removed using this check. */
    public function supportsTemplateTarget(string $key, ?string $target, ?Block $editorBlock = null): bool
    {
        $definition = $this->has($key) ? $this->find($key) : $editorBlock;

        if ($definition === null) {
            return false;
        }

        $targets = $definition instanceof HasTemplateTargets ? $definition->templateTargets() : null;

        if ($targets === null) {
            return true;
        }

        return isset(Template::TYPES[$target ?? '']) && in_array($target, $targets, true);
    }

    /** @return array<string> */
    public function templateKeys(?string $target): array
    {
        return array_values(array_filter(
            $this->keys(),
            fn (string $key): bool => $this->supportsTemplateTarget($key, $target),
        ));
    }

    /** @param array<Block> $blocks
     * @return array<Block>
     */
    public function filterForTemplate(array $blocks, ?string $target): array
    {
        return array_values(array_filter(
            $blocks,
            fn (Block $block): bool => $this->supportsTemplateTarget($block->getName(), $target, $block),
        ));
    }

    /** @return array<string, BlockNormalizer> */
    public function normalizers(): array
    {
        $normalizers = [];

        foreach ($this->keys() as $key) {
            $definition = $this->find($key);

            if ($definition instanceof BlockNormalizer) {
                $normalizers[$key] = $definition;
            }
        }

        return $normalizers;
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
