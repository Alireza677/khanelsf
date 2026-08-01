<?php

namespace App\CMS\Navigation;

use Closure;
use InvalidArgumentException;

final class NavigationSource
{
    private readonly Closure $resolver;

    private readonly Closure $availability;

    public function __construct(
        public readonly string $sourceKey,
        public readonly string $label,
        public readonly ?string $module,
        callable $resolver,
        callable $availability,
    ) {
        if (! preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)+$/', $sourceKey)) {
            throw new InvalidArgumentException("Invalid navigation source key [{$sourceKey}].");
        }

        if (blank($label)) {
            throw new InvalidArgumentException('A navigation source label is required.');
        }

        $this->resolver = Closure::fromCallable($resolver);
        $this->availability = Closure::fromCallable($availability);
    }

    public function isAvailable(): bool
    {
        return (bool) ($this->availability)();
    }

    public function resolve(): ?string
    {
        $url = ($this->resolver)();

        return filled($url) ? (string) $url : null;
    }

    /**
     * @return array{source_key: string, label: string, module: string|null, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'label' => $this->label,
            'module' => $this->module,
            'url' => $this->resolve(),
        ];
    }
}
