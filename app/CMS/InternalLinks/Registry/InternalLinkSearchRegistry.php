<?php

namespace App\CMS\InternalLinks\Registry;

use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Exceptions\DuplicateInternalLinkSource;
use App\CMS\InternalLinks\Exceptions\InvalidInternalLinkSource;

final class InternalLinkSearchRegistry
{
    /** @var array<string, InternalLinkSearchSource> */
    private array $sources = [];

    public function register(InternalLinkSearchSource $source): void
    {
        $key = $source->key();

        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw InvalidInternalLinkSource::forKey($key);
        }

        if ($this->has($key)) {
            throw DuplicateInternalLinkSource::forKey($key);
        }

        $this->sources[$key] = $source;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->sources);
    }

    public function get(string $key): ?InternalLinkSearchSource
    {
        return $this->sources[$key] ?? null;
    }

    /** @return array<string, InternalLinkSearchSource> */
    public function all(): array
    {
        return $this->sources;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->sources);
    }
}
