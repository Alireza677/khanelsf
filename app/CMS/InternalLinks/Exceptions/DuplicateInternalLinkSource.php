<?php

namespace App\CMS\InternalLinks\Exceptions;

use LogicException;

final class DuplicateInternalLinkSource extends LogicException
{
    public static function forKey(string $key): self
    {
        return new self("Internal link search source [{$key}] is already registered.");
    }
}
