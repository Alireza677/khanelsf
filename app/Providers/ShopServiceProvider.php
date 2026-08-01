<?php

namespace App\Providers;

use App\CMS\Actions\Contracts\RegistersActionTargets;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\ProductActionResolver;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use App\CMS\InternalLinks\Sources\ProductInternalLinkSource;
use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use App\Contracts\PaymentGateway;
use App\Services\ManualPaymentGateway;
use App\Services\ModuleService;
use App\Services\SettingsService;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Support\ServiceProvider;

class ShopServiceProvider extends ServiceProvider implements RegistersActionTargets
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            $settings = $app->make(SettingsService::class);

            return match ((string) $settings->get('payment_gateway', 'manual')) {
                'zarinpal' => $app->make(ZarinpalPaymentGateway::class),
                default => $app->make(ManualPaymentGateway::class),
            };
        });
    }

    public function boot(
        NavigationSourceRegistry $sources,
        ActionTargetRegistry $actionTargets,
        InternalLinkSearchRegistry $internalLinks,
        ProductInternalLinkSource $internalLinkSource,
        SettingsService $settings,
        ModuleService $modules,
    ): void {
        $this->registerActionTargets($actionTargets);
        $internalLinks->register($internalLinkSource);

        $label = $settings->get('shop_label', 'فروشگاه');

        $sources->register(new NavigationSource(
            sourceKey: 'shop.index',
            label: filled($label) ? (string) $label : 'فروشگاه',
            module: 'shop',
            resolver: fn (): string => route('shop.index', absolute: false),
            availability: fn (): bool => $modules->shopEnabled(),
        ));
    }

    public function registerActionTargets(ActionTargetRegistry $registry): void
    {
        $registry->register(new ActionTargetDefinition(
            key: CoreActionType::Product->value,
            resolver: ProductActionResolver::class,
        ));
    }
}
