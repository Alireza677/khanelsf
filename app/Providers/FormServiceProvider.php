<?php

namespace App\Providers;

use App\CMS\Actions\Contracts\RegistersActionTargets;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\FormActionResolver;
use Illuminate\Support\ServiceProvider;

final class FormServiceProvider extends ServiceProvider implements RegistersActionTargets
{
    public function boot(ActionTargetRegistry $actionTargets): void
    {
        $this->registerActionTargets($actionTargets);
    }

    public function registerActionTargets(ActionTargetRegistry $registry): void
    {
        $registry->register(new ActionTargetDefinition(
            key: CoreActionType::Form->value,
            resolver: FormActionResolver::class,
        ));
    }
}
