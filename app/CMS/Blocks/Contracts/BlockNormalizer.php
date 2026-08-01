<?php

namespace App\CMS\Blocks\Contracts;

interface BlockNormalizer
{
    public function normalize(array $data): array;
}
