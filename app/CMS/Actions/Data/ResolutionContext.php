<?php

namespace App\CMS\Actions\Data;

use App\CMS\Actions\Enums\ResolutionMode;

final readonly class ResolutionContext
{
    public function __construct(public ResolutionMode $mode = ResolutionMode::Production) {}

    public static function fromArray(array $data): self
    {
        $mode = is_string($data['mode'] ?? null)
            ? ResolutionMode::tryFrom($data['mode'])
            : null;

        return new self($mode ?? ResolutionMode::Production);
    }

    /** @return array{mode: string} */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value];
    }
}
