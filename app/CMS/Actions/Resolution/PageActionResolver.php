<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Support\PublicationStateReason;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

final class PageActionResolver extends AbstractEntityActionResolver
{
    private const PREVIEW_ROUTE = 'admin.preview.pages.show';

    public function actionType(): CoreActionType
    {
        return CoreActionType::Page;
    }

    protected function resolveReference(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        $page = Page::query()
            ->select(['id', 'slug', 'status', 'published_at'])
            ->find($destination->referenceId);

        if (! $page instanceof Page) {
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
                route(self::PREVIEW_ROUTE, $page, absolute: false),
            );
        }

        if (! Page::query()->published()->whereKey($page->getKey())->exists()) {
            return $this->unavailable(
                $destination,
                PublicationStateReason::for($page->status, $page->published_at),
            );
        }

        if (! Route::has($this->productionRoute($page))) {
            return $this->unavailable(
                $destination,
                ActionResolutionReason::RouteUnavailable,
            );
        }

        return $this->resolved($destination, $page->resolveNavigationUrl());
    }

    private function productionRoute(Page $page): string
    {
        return match ($page->slug) {
            'home' => 'home',
            'contact' => 'contact.create',
            default => 'pages.show',
        };
    }
}
