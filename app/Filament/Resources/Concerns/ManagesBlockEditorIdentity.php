<?php

namespace App\Filament\Resources\Concerns;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockEditorSaveManager;

trait ManagesBlockEditorIdentity
{
    public bool $heroV2EditorActive = false;

    public function bootManagesBlockEditorIdentity(): void
    {
        config()->set('cms.hero_v2_editor_runtime', $this->usesHeroV2Editor());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (is_array($data['blocks'] ?? null)) {
            $this->heroV2EditorActive = config('cms.hero_v2_editor', false)
                || $this->containsV2Hero($data['blocks']);
            config()->set('cms.hero_v2_editor_runtime', $this->heroV2EditorActive);

            $hydrator = app(BlockEditorHydrator::class);
            $data['blocks'] = $this->heroV2EditorActive
                ? $hydrator->hydrateV2($data['blocks'])
                : $hydrator->hydrate($data['blocks']);
        }

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->ensureBlockIdentity($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->ensureBlockIdentity($data);
    }

    private function ensureBlockIdentity(array $data): array
    {
        if (is_array($data['blocks'] ?? null)) {
            $data['blocks'] = app(BlockEditorSaveManager::class)->prepare(
                $data['blocks'],
                $this->usesHeroV2Editor(),
            );
        }

        return $data;
    }

    protected function usesHeroV2Editor(): bool
    {
        return $this->heroV2EditorActive || config('cms.hero_v2_editor', false);
    }

    private function containsV2Hero(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (is_array($block)
                && ($block['type'] ?? null) === 'hero'
                && (int) data_get($block, 'data.schema_version', 1) >= 2) {
                return true;
            }
        }

        return false;
    }
}
