<?php

namespace App\CMS\Blocks;

use Closure;
use Illuminate\Support\Str;

final class BlockIdentityManager
{
    private Closure $idFactory;

    public function __construct(?Closure $idFactory = null)
    {
        $this->idFactory = $idFactory ?? fn (): string => (string) Str::ulid();
    }

    public function ensureUniqueBlockIds(array $blocks): array
    {
        $seen = [];

        foreach ($blocks as $key => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || $block['type'] === '') {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $id = $data['block_id'] ?? null;

            $identityKey = is_string($id) ? strtoupper($id) : null;

            if (! $this->isValidId($id) || isset($seen[$identityKey])) {
                $id = $this->newUniqueId($seen);
                $data['block_id'] = $id;
                $block['data'] = $data;
                $blocks[$key] = $block;
                $identityKey = $id;
            }

            $seen[$identityKey] = true;
        }

        return $blocks;
    }

    public function regenerateBlockId(array $block): array
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $data['block_id'] = $this->newId();
        $block['data'] = $data;

        return $block;
    }

    public function regenerateDocumentIds(array $blocks): array
    {
        foreach ($blocks as $key => $block) {
            if (is_array($block) && is_string($block['type'] ?? null) && $block['type'] !== '') {
                $blocks[$key] = $this->regenerateBlockId($block);
            }
        }

        return $this->ensureUniqueBlockIds($blocks);
    }

    public function prepareClonedBlock(array $block): array
    {
        return $this->regenerateBlockId($block);
    }

    private function newUniqueId(array $seen): string
    {
        do {
            $id = $this->newId();
        } while (isset($seen[$id]));

        return $id;
    }

    private function newId(): string
    {
        return strtoupper((string) ($this->idFactory)());
    }

    private function isValidId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', strtoupper($id)) === 1;
    }
}
