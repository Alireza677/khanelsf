<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Enums\CoreActionType;

final class AnchorActionResolver extends AbstractValueActionResolver
{
    protected function actionType(): CoreActionType
    {
        return CoreActionType::Anchor;
    }

    protected function url(ActionDestination $destination): string
    {
        return '#'.$destination->value;
    }
}
