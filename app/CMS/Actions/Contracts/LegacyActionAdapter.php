<?php

namespace App\CMS\Actions\Contracts;

use App\CMS\Actions\Data\ActionDestination;

interface LegacyActionAdapter
{
    /**
     * Convert legacy destination data only. Presentation fields such as labels,
     * styles, icons, and layout remain the owning block's responsibility.
     */
    public function adapt(array $legacy): ActionDestination;
}
