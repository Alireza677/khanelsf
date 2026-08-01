<?php

namespace App\CMS\Actions\Enums;

enum ActionResolutionStatus: string
{
    case Resolved = 'resolved';
    case Unresolved = 'unresolved';
    case Invalid = 'invalid';
    case Unavailable = 'unavailable';
}
