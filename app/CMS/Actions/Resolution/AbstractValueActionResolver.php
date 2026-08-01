<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Contracts\ActionTargetResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Validation\ActionDestinationValidator;

abstract class AbstractValueActionResolver implements ActionTargetResolver
{
    public function __construct(
        private readonly ActionDestinationValidator $validator,
    ) {}

    final public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        if ($destination->coreType() !== $this->actionType()
            || $this->validator->validate($destination)->isInvalid()) {
            return ResolvedAction::invalid(
                $destination->type,
                ActionResolutionReason::InvalidDestination->value,
            );
        }

        return ResolvedAction::resolved(
            $this->actionType()->value,
            $this->url($destination),
            $this->actionType() === CoreActionType::CustomUrl
                && $destination->openInNewTab,
        );
    }

    abstract protected function actionType(): CoreActionType;

    abstract protected function url(ActionDestination $destination): string;
}
