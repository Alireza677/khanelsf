<?php

namespace App\Providers;

use App\CMS\Actions\Contracts\RegistersActionTargets;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\PageActionResolver;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use App\CMS\InternalLinks\Sources\PageInternalLinkSource;
use Illuminate\Support\ServiceProvider;

final class PageServiceProvider extends ServiceProvider implements RegistersActionTargets
{
    public function boot(
        ActionTargetRegistry $actionTargets,
        InternalLinkSearchRegistry $internalLinks,
        PageInternalLinkSource $internalLinkSource,
    ): void {
        $this->registerActionTargets($actionTargets);
        $internalLinks->register($internalLinkSource);
    }

    public function registerActionTargets(ActionTargetRegistry $registry): void
    {
        $registry->register(new ActionTargetDefinition(
            key: CoreActionType::Page->value,
            resolver: PageActionResolver::class,
        ));
    }
}
