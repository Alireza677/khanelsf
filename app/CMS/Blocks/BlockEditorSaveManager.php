<?php

namespace App\CMS\Blocks;

use App\CMS\Blocks\Hero\HeroMediaResolver;

final class BlockEditorSaveManager
{
    public function __construct(
        private readonly BlockEditorHydrator $hydrator,
        private readonly BlockIdentityManager $identities,
        private readonly HeroMediaResolver $mediaResolver,
    ) {}

    public function prepare(array $blocks, bool $useHeroV2): array
    {
        if (! $useHeroV2) {
            return $this->hydrator->hydrate($blocks);
        }

        $blocks = $this->hydrator->hydrateV2($blocks);

        foreach ($blocks as $key => $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'hero' || ! is_array($block['data'] ?? null)) {
                continue;
            }

            $selector = $block['data']['content']['selector'] ?? null;

            if (is_array($selector)
                && blank($selector['placeholder'] ?? null)
                && ($selector['items'] ?? []) === []) {
                $blocks[$key]['data']['content']['selector'] = null;
            } elseif (is_array($selector)) {
                $blocks[$key]['data']['content']['selector']['items'] = $this->canonicalItems(
                    $selector['items'] ?? [],
                    ['label', 'url'],
                );
            }

            $blocks[$key]['data']['content']['stats'] = $this->canonicalItems(
                $block['data']['content']['stats'] ?? [],
                ['value', 'label', 'description', 'icon', 'icon_size'],
            );
            $blocks[$key]['data']['content']['social_links'] = $this->canonicalItems(
                $block['data']['content']['social_links'] ?? [],
                ['label', 'url', 'icon', 'icon_size'],
            );

            if (data_get($blocks[$key], 'data.settings.background_effect.settings.line_width') === null) {
                unset($blocks[$key]['data']['settings']['background_effect']['settings']['line_width']);
            }

            $media = $block['data']['content']['media'] ?? null;

            if (! is_array($media)) {
                continue;
            }

            $media['source_id'] = $this->mediaResolver->resolveSourceId(
                is_string($media['url'] ?? null) ? $media['url'] : null,
            );
            $media['poster_source_id'] = $this->mediaResolver->resolveSourceId(
                is_string($media['poster_url'] ?? null) ? $media['poster_url'] : null,
            );
            $blocks[$key]['data']['content']['media'] = $media;
        }

        return $blocks;
    }

    private function canonicalItems(mixed $items, array $keys): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(static function ($item) use ($keys): array {
            $item = is_array($item) ? $item : [];

            return array_combine($keys, array_map(
                static fn (string $key): mixed => $item[$key] ?? null,
                $keys,
            ));
        }, $items));
    }
}
