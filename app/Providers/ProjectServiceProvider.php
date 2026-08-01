<?php

namespace App\Providers;

use App\CMS\Actions\Contracts\RegistersActionTargets;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\ProjectActionResolver;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use App\CMS\InternalLinks\Sources\ProjectInternalLinkSource;
use Illuminate\Support\ServiceProvider;

final class ProjectServiceProvider extends ServiceProvider implements RegistersActionTargets
{
    public function boot(
        ActionTargetRegistry $actionTargets,
        InternalLinkSearchRegistry $internalLinks,
        ProjectInternalLinkSource $internalLinkSource,
    ): void {
        $this->registerActionTargets($actionTargets);
        $internalLinks->register($internalLinkSource);
    }

    public function registerActionTargets(ActionTargetRegistry $registry): void
    {
        $registry->register(new ActionTargetDefinition(
            key: CoreActionType::Project->value,
            resolver: ProjectActionResolver::class,
        ));
    }
}
