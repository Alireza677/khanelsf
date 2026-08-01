<?php

namespace App\CMS\Actions\Enums;

enum ResolutionMode: string
{
    case Production = 'production';
    case Preview = 'preview';
}
