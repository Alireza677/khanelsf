<?php

namespace App\CMS\Actions\Data;

use App\CMS\Actions\Contracts\ActionTargetResolver;
use App\CMS\Actions\Exceptions\InvalidActionTargetDefinition;

final readonly class ActionTargetDefinition
{
    /**
     * @param  class-string<ActionTargetResolver>  $resolver
     */
    public function __construct(
        public string $key,
        public string $resolver,
        public bool $referenceBased = true,
    ) {
        if (! self::isValidKey($key)) {
            throw InvalidActionTargetDefinition::invalidKey($key);
        }

        if (! is_a($resolver, ActionTargetResolver::class, true)) {
            throw InvalidActionTargetDefinition::invalidResolver($key);
        }
    }

    public static function isValidKey(string $key): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $key) === 1;
    }
}
