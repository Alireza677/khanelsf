<?php

namespace App\CMS\Actions\Exceptions;

use InvalidArgumentException;

final class InvalidActionTargetDefinition extends InvalidArgumentException
{
    public static function invalidKey(string $key): self
    {
        return new self("Action target key [{$key}] must be a canonical lowercase identifier.");
    }

    public static function invalidResolver(string $key): self
    {
        return new self("Action target [{$key}] must use an ActionTargetResolver class.");
    }
}
