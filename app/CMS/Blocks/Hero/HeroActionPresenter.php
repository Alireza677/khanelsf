<?php

namespace App\CMS\Blocks\Hero;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;

final class HeroActionPresenter
{
    public function __construct(
        private readonly ActionResolver $resolver,
        private readonly ActionPresentation $presentation,
    ) {}

    /** @param array<string, mixed> $hero @param array<string, mixed> $context */
    public function prepare(array $hero, array $context = [], bool $preview = false): array
    {
        $resolution = new ResolutionContext($preview ? ResolutionMode::Preview : ResolutionMode::Production);
        $presentationContext = [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? request()->getRequestUri(),
            'block_id' => $hero['block_id'] ?? null,
        ];
        $present = fn (mixed $action): ?array => is_array($action)
            ? $this->presentation->present($this->resolver->resolve(ActionDestination::fromArray($action), $resolution), $presentationContext)
            : null;

        foreach (['primary_cta', 'secondary_cta'] as $name) {
            // Hero 2's primary CTA is only a trigger for the selected option.
            // Keep persisted legacy state intact, but never resolve or present it.
            $hero['content'][$name]['presentation'] = $name === 'primary_cta' && ($hero['template'] ?? null) === 'hero_2'
                ? null
                : $present($hero['content'][$name]['action'] ?? null);
        }
        foreach (['social_links', 'selector.items'] as $path) {
            $items = data_get($hero['content'], $path, []);
            foreach ($items as &$item) {
                $item['presentation'] = $present($item['action'] ?? null);
            }
            unset($item);
            data_set($hero['content'], $path, $items);
        }

        return $hero;
    }
}
