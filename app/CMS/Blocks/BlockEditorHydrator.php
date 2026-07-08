<?php

namespace App\CMS\Blocks;

use App\CMS\Blocks\Hero\HeroDataNormalizer;

final class BlockEditorHydrator
{
    public function __construct(
        private readonly BlockIdentityManager $identities,
        private readonly HeroDataNormalizer $heroes,
    ) {}

    /**
     * Active production mode while the editor schema is still legacy-flat.
     */
    public function hydrate(array $blocks): array
    {
        return $this->identities->ensureUniqueBlockIds($blocks);
    }

    /**
     * Explicit future-v2 mode. This is not connected to the production editor.
     */
    public function hydrateV2(array $blocks): array
    {
        foreach ($blocks as $key => $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'hero') {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $normalized = $this->heroes->normalize($data);
            $blocks[$key]['data'] = array_intersect_key($normalized, array_flip([
                'block_id',
                'schema_version',
                'template',
                'content',
                'settings',
            ]));
        }

        return $this->identities->ensureUniqueBlockIds($blocks);
    }
}
