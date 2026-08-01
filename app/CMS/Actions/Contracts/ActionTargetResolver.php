<?php

namespace App\CMS\Actions\Contracts;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;

interface ActionTargetResolver
{
    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction;
}
