<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Contracts\EntityActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;

abstract class AbstractEntityActionResolver implements EntityActionResolver
{
    final public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        $actionType = $this->actionType()->value;

        if ($destination->schemaVersion !== ActionDestination::SCHEMA_VERSION) {
            return ResolvedAction::invalid(
                $destination->type,
                ActionResolutionReason::UnsupportedSchemaVersion->value,
            );
        }

        if ($destination->coreType() !== $this->actionType()) {
            return ResolvedAction::invalid(
                $destination->type,
                ActionResolutionReason::UnsupportedActionType->value,
            );
        }

        if ($destination->referenceId === null) {
            return ResolvedAction::invalid(
                $actionType,
                ActionResolutionReason::MissingReferenceId->value,
            );
        }

        if ($destination->referenceId <= 0) {
            return ResolvedAction::invalid(
                $actionType,
                ActionResolutionReason::InvalidReferenceId->value,
            );
        }

        return $this->resolveReference($destination, $context);
    }

    abstract protected function resolveReference(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction;

    protected function resolved(ActionDestination $destination, ?string $url): ResolvedAction
    {
        $url = is_string($url) ? trim($url) : '';

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::UrlResolutionFailed,
            );
        }

        return ResolvedAction::resolved(
            $this->actionType()->value,
            $url,
            $destination->openInNewTab,
            ['reference_id' => $destination->referenceId],
        );
    }

    protected function unresolved(
        ActionDestination $destination,
        ActionResolutionReason $reason = ActionResolutionReason::EntityNotFound,
    ): ResolvedAction {
        return ResolvedAction::unresolved(
            $this->actionType()->value,
            $reason->value,
            ['reference_id' => $destination->referenceId],
        );
    }

    protected function unavailable(
        ActionDestination $destination,
        ActionResolutionReason $reason,
    ): ResolvedAction {
        return ResolvedAction::unavailable(
            $this->actionType()->value,
            $reason->value,
            ['reference_id' => $destination->referenceId],
        );
    }
}
