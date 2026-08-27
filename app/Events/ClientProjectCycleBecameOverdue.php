<?php

namespace App\Events;

use App\Models\ClientProjectCycle;

final class ClientProjectCycleBecameOverdue
{
    public function __construct(public readonly ClientProjectCycle $cycle) {}
}
