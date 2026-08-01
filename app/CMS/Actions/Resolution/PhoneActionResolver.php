<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Enums\CoreActionType;

final class PhoneActionResolver extends AbstractValueActionResolver
{
    protected function actionType(): CoreActionType
    {
        return CoreActionType::Phone;
    }

    protected function url(ActionDestination $destination): string
    {
        return 'tel:'.$destination->value;
    }
}
