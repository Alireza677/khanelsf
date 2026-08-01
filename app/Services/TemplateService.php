<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Template;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class TemplateService
{
    public function findTemplateFor(string $type, ?Model $model = null): ?Template
    {
        $templates = $this->matchingCandidates($type, $model);

        return $templates->first()['template'] ?? null;
    }

    public function explainMatch(string $type, ?Model $model = null): array
    {
        $candidates = $this->matchingCandidates($type, $model);
        $matched = $candidates->first();

        return [
            'matched_template' => $matched['template'] ?? null,
            'specificity' => $matched['specificity'] ?? 0,
            'reason' => $matched
                ? $this->specificityLabel((int) $matched['specificity'])
                : 'No published template matched this type and context.',
            'candidates' => $candidates
                ->map(fn (array $candidate): array => [
                    'id' => $candidate['template']->id,
                    'title' => $candidate['template']->title,
                    'condition' => $candidate['template']->conditionSummary(),
                    'priority' => $candidate['template']->priority,
                    'specificity' => $candidate['specificity'],
                ])
                ->values()
                ->all(),
        ];
    }

    private function matchingCandidates(string $type, ?Model $model = null)
    {
        return Template::query()
            ->published()
            ->where('type', $type)
            ->get()
            ->map(fn (Template $template): array => [
                'template' => $template,
                'specificity' => $this->specificityFor($template, $model),
            ])
            ->filter(fn (array $match): bool => $match['specificity'] > 0)
            ->sort(function (array $left, array $right): int {
                foreach ([
                    $right['specificity'] <=> $left['specificity'],
                    $right['template']->priority <=> $left['template']->priority,
                    ($right['template']->updated_at?->getTimestamp() ?? 0) <=> ($left['template']->updated_at?->getTimestamp() ?? 0),
                    $right['template']->getKey() <=> $left['template']->getKey(),
                ] as $comparison) {
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            });
    }

    public function viewOrFallback(?Template $template, string $fallbackView, array $data = []): View
    {
        if ($template?->hasBlocks()) {
            return view('templates.render', [
                ...$data,
                'template' => $template,
                'templateContext' => $data['templateContext'] ?? [],
            ]);
        }

        return view($fallbackView, [
            ...$data,
            'template' => null,
        ]);
    }

    private function specificityFor(Template $template, ?Model $model = null): int
    {
        $conditionType = $template->conditionType();

        if ($conditionType === 'specific_item') {
            return $this->matchesSpecificItem($template, $model) ? 3 : 0;
        }

        if ($conditionType === 'category') {
            return $this->matchesCategory($template, $model) ? 2 : 0;
        }

        return $template->is_default
            && ($conditionType === 'all' || blank($template->conditions))
                ? 1
                : 0;
    }

    private function specificityLabel(int $specificity): string
    {
        return match ($specificity) {
            3 => 'Matched by specific item condition.',
            2 => 'Matched by category condition.',
            1 => 'Matched by all/default condition.',
            default => 'No match.',
        };
    }

    private function matchesSpecificItem(Template $template, ?Model $model): bool
    {
        $itemId = (int) ($template->conditions['item_id'] ?? 0);

        if ($itemId < 1 || ! $model) {
            return false;
        }

        return match ($template->type) {
            'post_single' => $model instanceof Post && $model->getKey() === $itemId,
            'project_single' => $model instanceof Project && $model->getKey() === $itemId,
            'product_single' => $model instanceof Product && $model->getKey() === $itemId,
            'service_single' => $model instanceof Service && $model->getKey() === $itemId,
            'gallery_single' => $model instanceof Gallery && $model->getKey() === $itemId,
            'post_category' => $model instanceof Category && $model->getKey() === $itemId,
            'project_category' => $model instanceof ProjectCategory && $model->getKey() === $itemId,
            'product_category' => $model instanceof ProductCategory && $model->getKey() === $itemId,
            'gallery_category' => $model instanceof GalleryCategory && $model->getKey() === $itemId,
            default => false,
        };
    }

    private function matchesCategory(Template $template, ?Model $model): bool
    {
        $categoryId = (int) ($template->conditions['category_id'] ?? 0);

        if ($categoryId < 1 || ! $model) {
            return false;
        }

        return match ($template->type) {
            'post_single' => $model instanceof Post && (int) $model->category_id === $categoryId,
            'project_single' => $model instanceof Project && (int) $model->project_category_id === $categoryId,
            'product_single' => $model instanceof Product && (int) $model->product_category_id === $categoryId,
            'gallery_single' => $model instanceof Gallery && (int) $model->gallery_category_id === $categoryId,
            'post_category' => $model instanceof Category && $model->getKey() === $categoryId,
            'project_category' => $model instanceof ProjectCategory && $model->getKey() === $categoryId,
            'product_category' => $model instanceof ProductCategory && $model->getKey() === $categoryId,
            'gallery_category' => $model instanceof GalleryCategory && $model->getKey() === $categoryId,
            default => false,
        };
    }
}
