<?php

namespace Database\Seeders;

use App\CMS\Blocks\BlockIdentityManager;
use App\CMS\Blocks\ProjectDiscovery\ProjectDiscoveryGridBlock;
use App\Models\Template;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProjectDiscoveryTemplateSeeder extends Seeder
{
    public const SLUG = 'project-discovery-index-template';

    public function run(): void
    {
        $existing = Template::query()->where('slug', self::SLUG)->first();

        if ($existing) {
            if ($existing->type !== 'project_discovery_index') {
                throw new RuntimeException('The project discovery template slug is already used by another template type.');
            }

            return;
        }

        if (Template::query()
            ->where('type', 'project_discovery_index')
            ->where('status', 'published')
            ->where('is_default', true)
            ->exists()) {
            throw new RuntimeException('A published default Project Discovery template already exists; cloning was stopped.');
        }

        $source = Template::query()->find(4);

        if (! $source || $source->type !== 'galleries_index') {
            throw new RuntimeException('Historical Galleries Index Template #4 was not found in the expected state.');
        }

        $blocks = collect($source->blocks ?? [])->map(function (mixed $block): ?array {
            if (! is_array($block)) {
                return null;
            }

            if (($block['type'] ?? null) === 'template_archive_header') {
                return $block;
            }

            if (($block['type'] ?? null) === 'template_content_grid') {
                return [
                    'type' => 'project_discovery_grid',
                    'data' => app(ProjectDiscoveryGridBlock::class)->normalize([
                        'settings' => [
                            'show_filters' => true,
                            'columns' => 3,
                            'image_ratio' => 'landscape',
                            'show_category' => true,
                            'show_discovery_terms' => true,
                        ],
                    ]),
                ];
            }

            return null;
        })->filter()->values()->all();

        if (! collect($blocks)->contains(fn (array $block): bool => ($block['type'] ?? null) === 'project_discovery_grid')) {
            throw new RuntimeException('Historical Template #4 has no translatable content grid.');
        }

        Template::query()->create([
            'title' => 'Project Discovery Index Template',
            'slug' => self::SLUG,
            'type' => 'project_discovery_index',
            'status' => $source->status,
            'is_default' => $source->is_default,
            'priority' => $source->priority,
            'conditions' => $source->conditions ?? ['type' => 'all'],
            'blocks' => app(BlockIdentityManager::class)->regenerateDocumentIds($blocks),
        ]);
    }
}
