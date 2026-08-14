<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Support\PublicationStateReason;
use App\Models\Service;
use App\Services\ServiceSettings;
use Illuminate\Support\Facades\Route;

final class ServiceActionResolver extends AbstractEntityActionResolver
{
    private const PRODUCTION_ROUTE = 'services.show';

    public function __construct(private readonly ServiceSettings $settings) {}

    public function actionType(): CoreActionType
    {
        return CoreActionType::Service;
    }

    protected function resolveReference(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        if (! $this->settings->publicEnabled()) {
            return $this->unavailable($destination, ActionResolutionReason::ModuleDisabled);
        }

        $service = Service::query()
            ->select(['id', 'slug', 'status', 'published_at'])
            ->find($destination->referenceId);

        if (! $service instanceof Service) {
            return $this->unresolved($destination);
        }

        if ($context->mode === ResolutionMode::Preview) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::PreviewUnavailable,
            );
        }

        if (! $service->isPublished()) {
            return $this->unavailable(
                $destination,
                PublicationStateReason::for($service->status, $service->published_at),
            );
        }

        if (! Route::has(self::PRODUCTION_ROUTE)) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::RouteUnavailable,
            );
        }

        return $this->resolved($destination, $service->resolveNavigationUrl());
    }
}
