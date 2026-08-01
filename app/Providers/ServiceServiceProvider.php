<?php

namespace App\Providers;

use App\CMS\Actions\Contracts\RegistersActionTargets;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\ServiceActionResolver;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use App\CMS\InternalLinks\Sources\ServiceInternalLinkSource;
use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ServiceServiceProvider extends ServiceProvider implements RegistersActionTargets
{
    public function boot(
        NavigationSourceRegistry $sources,
        ActionTargetRegistry $actionTargets,
        InternalLinkSearchRegistry $internalLinks,
        ServiceInternalLinkSource $internalLinkSource,
    ): void {
        $this->registerActionTargets($actionTargets);
        $internalLinks->register($internalLinkSource);

        $sources->register(new NavigationSource(
            sourceKey: 'services.archive',
            label: 'خدمات',
            module: 'services',
            resolver: fn (): ?string => Route::has('services.index')
                ? route('services.index', absolute: false)
                : null,
            availability: fn (): bool => Route::has('services.index'),
        ));
    }

    public function registerActionTargets(ActionTargetRegistry $registry): void
    {
        $registry->register(new ActionTargetDefinition(
            key: CoreActionType::Service->value,
            resolver: ServiceActionResolver::class,
        ));
    }
}
