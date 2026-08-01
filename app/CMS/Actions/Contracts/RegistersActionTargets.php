<?php

namespace App\CMS\Actions\Contracts;

use App\CMS\Actions\Registry\ActionTargetRegistry;

interface RegistersActionTargets
{
    public function registerActionTargets(ActionTargetRegistry $registry): void;
}
