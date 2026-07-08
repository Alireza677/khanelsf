<?php

namespace App\CMS\Blocks\Support;

final readonly class BlockTemplate
{
    /**
     * @param  array<string>  $capabilities
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $view,
        public ?string $thumbnail = null,
        public array $capabilities = [],
    ) {}
}
