<?php

namespace App\CMS\InternalLinks\Exceptions;

use InvalidArgumentException;

final class InvalidInternalLinkSource extends InvalidArgumentException
{
    public static function forKey(string $key): self
    {
        return new self("Internal link search source key [{$key}] must be canonical.");
    }
}
