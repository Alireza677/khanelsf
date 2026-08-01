<?php

namespace App\CMS\Navigation\Contracts;

interface ResolvesNavigationUrl
{
    public function resolveNavigationUrl(): ?string;
}
