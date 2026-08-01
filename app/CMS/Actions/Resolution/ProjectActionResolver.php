<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Support\PublicationStateReason;
use App\Models\Project;
use App\Services\ModuleService;
use Illuminate\Support\Facades\Route;

final class ProjectActionResolver extends AbstractEntityActionResolver
{
    private const PREVIEW_ROUTE = 'admin.preview.projects.show';

    private const PRODUCTION_ROUTE = 'projects.show';

    public function __construct(private readonly ModuleService $modules) {}

    public function actionType(): CoreActionType
    {
        return CoreActionType::Project;
    }

    protected function resolveReference(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        if (! $this->modules->projectsEnabled()) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::ModuleDisabled,
            );
        }

        $project = Project::query()
            ->select(['id', 'slug', 'status', 'published_at'])
            ->find($destination->referenceId);

        if (! $project instanceof Project) {
            return $this->unresolved($destination);
        }

        if ($context->mode === ResolutionMode::Preview) {
            if (! Route::has(self::PREVIEW_ROUTE)) {
                return $this->unavailable(
                    $destination,
                    ActionResolutionReason::PreviewUnavailable,
                );
            }

            return $this->resolved(
                $destination,
                route(self::PREVIEW_ROUTE, $project, absolute: false),
            );
        }

        if (! Project::query()->published()->whereKey($project->getKey())->exists()) {
            return $this->unavailable(
                $destination,
                PublicationStateReason::for($project->status, $project->published_at),
            );
        }

        if (! Route::has(self::PRODUCTION_ROUTE)) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::RouteUnavailable,
            );
        }

        return $this->resolved($destination, $project->resolveNavigationUrl());
    }
}
