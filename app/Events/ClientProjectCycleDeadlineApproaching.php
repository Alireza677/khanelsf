<?php

namespace App\Events;

use App\Models\ClientProjectCycle;

final class ClientProjectCycleDeadlineApproaching
{
    public function __construct(public readonly ClientProjectCycle $cycle) {}
}
