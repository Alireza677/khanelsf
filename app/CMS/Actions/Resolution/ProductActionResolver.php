<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Support\PublicationStateReason;
use App\Models\Product;
use App\Services\ModuleService;
use Illuminate\Support\Facades\Route;

final class ProductActionResolver extends AbstractEntityActionResolver
{
    private const PREVIEW_ROUTE = 'admin.preview.products.show';

    private const PRODUCTION_ROUTE = 'shop.show';

    public function __construct(private readonly ModuleService $modules) {}

    public function actionType(): CoreActionType
    {
        return CoreActionType::Product;
    }

    protected function resolveReference(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        if (! $this->modules->shopEnabled()) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::ModuleDisabled,
            );
        }

        $product = Product::query()
            ->select(['id', 'slug', 'status', 'published_at'])
            ->find($destination->referenceId);

        if (! $product instanceof Product) {
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
                route(self::PREVIEW_ROUTE, $product, absolute: false),
            );
        }

        if (! Product::query()->published()->whereKey($product->getKey())->exists()) {
            return $this->unavailable(
                $destination,
                PublicationStateReason::for($product->status, $product->published_at),
            );
        }

        if (! Route::has(self::PRODUCTION_ROUTE)) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::RouteUnavailable,
            );
        }

        return $this->resolved($destination, $product->resolveNavigationUrl());
    }
}
