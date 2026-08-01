<?php

namespace App\Providers;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Contracts\RegistersActionTargets;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\AnchorActionResolver;
use App\CMS\Actions\Resolution\CustomUrlActionResolver;
use App\CMS\Actions\Resolution\EmailActionResolver;
use App\CMS\Actions\Resolution\PhoneActionResolver;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class ActionServiceProvider extends ServiceProvider implements RegistersActionTargets
{
    public function register(): void
    {
        $this->app->singleton(
            ActionTargetRegistry::class,
            fn (): ActionTargetRegistry => new ActionTargetRegistry,
        );

        $this->app->singleton(
            RuntimeActionResolver::class,
            fn (Application $app): RuntimeActionResolver => new RuntimeActionResolver(
                $app->make(ActionTargetRegistry::class),
                $app,
                $app->make(LoggerInterface::class),
            ),
        );

        $this->app->alias(RuntimeActionResolver::class, ActionResolver::class);
    }

    public function boot(ActionTargetRegistry $registry): void
    {
        $this->registerActionTargets($registry);
    }

    public function registerActionTargets(ActionTargetRegistry $registry): void
    {
        foreach ([
            CoreActionType::CustomUrl->value => CustomUrlActionResolver::class,
            CoreActionType::Anchor->value => AnchorActionResolver::class,
            CoreActionType::Email->value => EmailActionResolver::class,
            CoreActionType::Phone->value => PhoneActionResolver::class,
        ] as $key => $resolver) {
            $registry->register(new ActionTargetDefinition(
                key: $key,
                resolver: $resolver,
                referenceBased: false,
            ));
        }
    }
}
