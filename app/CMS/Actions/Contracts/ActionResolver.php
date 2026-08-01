<?php

namespace App\CMS\Actions\Contracts;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;

interface ActionResolver
{
    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction;

    /**
     * @param  iterable<mixed>  $destinations
     * @return array<int, ResolvedAction>
     */
    public function resolveMany(
        iterable $destinations,
        ResolutionContext $context,
    ): array;
}
