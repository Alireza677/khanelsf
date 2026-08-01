<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Enums\CoreActionType;

final class CustomUrlActionResolver extends AbstractValueActionResolver
{
    protected function actionType(): CoreActionType
    {
        return CoreActionType::CustomUrl;
    }

    protected function url(ActionDestination $destination): string
    {
        return (string) $destination->value;
    }
}
