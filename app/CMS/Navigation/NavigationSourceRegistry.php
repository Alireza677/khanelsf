<?php

namespace App\CMS\Navigation;

use LogicException;

final class NavigationSourceRegistry
{
    /**
     * @var array<string, NavigationSource>
     */
    private array $sources = [];

    public function register(NavigationSource $source): void
    {
        if ($this->exists($source->sourceKey)) {
            throw new LogicException("Navigation source [{$source->sourceKey}] is already registered.");
        }

        $this->sources[$source->sourceKey] = $source;
    }

    public function find(?string $sourceKey): ?NavigationSource
    {
        if (blank($sourceKey)) {
            return null;
        }

        return $this->sources[$sourceKey] ?? null;
    }

    public function exists(?string $sourceKey): bool
    {
        return filled($sourceKey) && isset($this->sources[$sourceKey]);
    }

    /**
     * @return array<string, NavigationSource>
     */
    public function all(): array
    {
        return $this->sources;
    }

    /**
     * @return array<string, NavigationSource>
     */
    public function available(): array
    {
        return array_filter(
            $this->sources,
            fn (NavigationSource $source): bool => $source->isAvailable(),
        );
    }

    public function isAvailable(?string $sourceKey): bool
    {
        return $this->find($sourceKey)?->isAvailable() ?? false;
    }

    public function resolve(?string $sourceKey): ?string
    {
        $source = $this->find($sourceKey);

        if (! $source) {
            return null;
        }

        return $source->resolve();
    }
}
