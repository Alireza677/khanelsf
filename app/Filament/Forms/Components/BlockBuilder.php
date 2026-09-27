<?php

namespace App\Filament\Forms\Components;

use App\CMS\Blocks\BlockRegistry;
use Closure;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Notifications\Notification;

final class BlockBuilder extends Builder
{
    protected string $view = 'filament.forms.components.block-builder-editor';

    protected string|Closure|null $templateTarget = null;

    protected bool $filtersTemplateTargets = false;

    public function templateTarget(string|Closure|null $target): static
    {
        $this->templateTarget = $target;
        $this->filtersTemplateTargets = true;

        return $this;
    }

    public function getBlockPickerBlocks(): array
    {
        $blocks = parent::getBlockPickerBlocks();

        return $this->filtersTemplateTargets
            ? app(BlockRegistry::class)->filterForTemplate($blocks, $this->evaluate($this->templateTarget))
            : $blocks;
    }

    public function canAddBlock(string $key): bool
    {
        $block = $this->getBlock($key);

        return $block !== null && (! $this->filtersTemplateTargets
            || app(BlockRegistry::class)->supportsTemplateTarget($key, $this->evaluate($this->templateTarget), $block));
    }

    public function getAddAction(): Action
    {
        return $this->guardAddition(parent::getAddAction());
    }

    public function getAddBetweenAction(): Action
    {
        return $this->guardAddition(parent::getAddBetweenAction());
    }

    public function getCloneAction(): Action
    {
        $action = parent::getCloneAction();

        if (! $this->filtersTemplateTargets) {
            return $action;
        }

        return $this->guardAddition($action, cloning: true)
            ->visible(function (array $arguments, BlockBuilder $component): bool {
                $key = $component->getState()[$arguments['item'] ?? '']['type'] ?? null;

                return $component->isCloneable() && is_string($key) && $component->canAddBlock($key);
            });
    }

    private function guardAddition(Action $action, bool $cloning = false): Action
    {
        if (! $this->filtersTemplateTargets) {
            return $action;
        }

        // Check at execution time too: a picker may have opened before the target changed.
        return $action->before(function (array $arguments, BlockBuilder $component, Action $action) use ($cloning): void {
            $key = $cloning
                ? ($component->getState()[$arguments['item'] ?? '']['type'] ?? null)
                : ($arguments['block'] ?? null);

            if (! is_string($key) || ! $component->canAddBlock($key)) {
                Notification::make()->warning()->title(__('This block is not available for the selected template type.'))->send();
                $action->cancel();
            }
        });
    }
}
