<?php

namespace App\CMS\Blocks;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Blocks\Hero\HeroDataNormalizer;

final class BlockEditorHydrator
{
    public function __construct(
        private readonly BlockIdentityManager $identities,
        private readonly HeroDataNormalizer $heroes,
        private readonly CTADataNormalizer $ctas,
        private readonly ?BlockRegistry $registry = null,
    ) {}

    /**
     * Active production mode while the editor schema is still legacy-flat.
     */
    public function hydrate(array $blocks): array
    {
        return $this->identities->ensureUniqueBlockIds($this->normalizeBlocks($blocks, [
            ...($this->registry?->normalizers() ?? []),
            'cta' => $this->ctas,
        ]));
    }

    /**
     * Explicit future-v2 mode. This is not connected to the production editor.
     */
    public function hydrateV2(array $blocks): array
    {
        return $this->identities->ensureUniqueBlockIds($this->normalizeBlocks($blocks, [
            ...($this->registry?->normalizers() ?? []),
            'hero' => $this->heroes,
            'cta' => $this->ctas,
        ]));
    }

    /** @param array<string, BlockNormalizer> $normalizers */
    private function normalizeBlocks(array $blocks, array $normalizers): array
    {
        foreach ($blocks as $key => $block) {
            if (! is_array($block)) {
                continue;
            }

            $normalizer = $normalizers[$block['type'] ?? ''] ?? null;

            if ($normalizer === null) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $blocks[$key]['data'] = array_intersect_key(
                $normalizer->normalize($data),
                array_flip(['block_id', 'schema_version', 'template', 'content', 'settings']),
            );
        }

        return $blocks;
    }
}
