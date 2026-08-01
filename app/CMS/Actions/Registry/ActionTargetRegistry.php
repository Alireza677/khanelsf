<?php

namespace App\CMS\Actions\Registry;

use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Exceptions\DuplicateActionTarget;

final class ActionTargetRegistry
{
    /** @var array<string, ActionTargetDefinition> */
    private array $definitions = [];

    public function register(ActionTargetDefinition $definition): void
    {
        if ($this->has($definition->key)) {
            throw DuplicateActionTarget::forKey($definition->key);
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitions);
    }

    public function get(string $key): ?ActionTargetDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return array<string, ActionTargetDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->definitions);
    }
}
