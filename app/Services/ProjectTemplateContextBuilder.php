<?php

namespace App\Services;

use App\CMS\Blocks\Project\RelatedProjectsBlock;
use App\Models\Gallery;
use App\Models\Project;
use App\Models\Template;
use Illuminate\Support\Collection;

final class ProjectTemplateContextBuilder
{
    public function __construct(
        private readonly RelatedProjectsBlock $relatedProjectsBlock,
    ) {}

    public function build(Project $project, ?Template $template, bool $galleriesEnabled): array
    {
        $project->loadMissing([
            'category' => fn ($query) => $query->active(),
            'metrics',
            'relatedServices' => fn ($query) => $query->published(),
            'media',
            'videos',
        ]);

        $relatedProjects = $this->relatedProjects($project, $this->relatedLimit($template));
        $projectGalleries = $this->projectGalleries(
            $project,
            $galleriesEnabled && ! $template?->hasBlocks(),
        );

        return [
            'project' => $project,
            'relatedProjects' => $relatedProjects,
            'projectGalleries' => $projectGalleries,
            'projectVideos' => $project->videos,
            'templateContext' => [
                'kind' => 'single',
                'type' => 'project',
                'model' => $project,
                'related' => $relatedProjects,
                'projectGalleries' => $projectGalleries,
                'videos' => $project->videos,
            ],
        ];
    }

    public function relatedLimit(?Template $template): int
    {
        if (! $template?->hasBlocks()) {
            return 3;
        }

        $block = collect($template->blocks)
            ->first(fn (mixed $block): bool => is_array($block)
                && ($block['type'] ?? null) === $this->relatedProjectsBlock->key());

        if (! is_array($block)) {
            return 0;
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : $block;

        return $this->relatedProjectsBlock->normalize($data)['settings']['limit'];
    }

    private function relatedProjects(Project $project, int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return Project::query()
            ->with([
                'category' => fn ($query) => $query->active(),
                'media',
            ])
            ->published()
            ->whereKeyNot($project->getKey())
            ->when(
                $project->project_category_id,
                fn ($query) => $query->where('project_category_id', $project->project_category_id),
            )
            ->orderBy('sort_order')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private function projectGalleries(Project $project, bool $enabled): Collection
    {
        if (! $enabled) {
            return collect();
        }

        return Gallery::query()
            ->with(['category', 'media'])
            ->published()
            ->withPublicCategory()
            ->where('project_id', $project->getKey())
            ->orderBy('sort_order')
            ->latest('published_at')
            ->limit(6)
            ->get();
    }
}
