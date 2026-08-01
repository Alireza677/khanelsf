<?php

namespace App\CMS\Actions\Contracts;

use App\CMS\Actions\Enums\CoreActionType;

interface EntityActionResolver extends ActionTargetResolver
{
    public function actionType(): CoreActionType;
}
