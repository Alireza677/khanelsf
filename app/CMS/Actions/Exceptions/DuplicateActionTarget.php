<?php

namespace App\CMS\Actions\Exceptions;

use LogicException;

final class DuplicateActionTarget extends LogicException
{
    public static function forKey(string $key): self
    {
        return new self("Action target [{$key}] is already registered.");
    }
}
