<?php

namespace App\CMS\Blocks\FeatureGrid;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\Models\Post;
use App\Models\Project;

final class FeatureGridRuntime
{
    public function __construct(
        private readonly FeatureGridDataNormalizer $normalizer,
        private readonly RuntimeActionResolver $actions,
        private readonly ActionPresentation $presentation,
        private readonly FeatureGridVideoResolver $videos,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function prepare(
        array $data,
        array $context = [],
        bool $preview = false,
    ): array {
        $grid = $this->normalizer->normalize($data);
        $content = $grid['content'];
        $settings = $grid['settings'];
        $dynamic = $content['items_mode'] === 'dynamic';
        $items = $dynamic
            ? $this->dynamicItems($content, $settings)
            : $this->videos->resolve($content['items'], $preview);
        $destinations = array_map(
            fn (array $item): ActionDestination => ActionDestination::fromArray(
                is_array($item['action'] ?? null) ? $item['action'] : [],
            ),
            $items,
        );
        $resolved = $this->actions->resolveMany(
            $destinations,
            new ResolutionContext(
                $preview ? ResolutionMode::Preview : ResolutionMode::Production,
            ),
        );
        $actionContext = [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? null,
            'block_id' => $grid['block_id'],
        ];

        foreach ($items as $index => $item) {
            $items[$index]['presentation'] = $this->presentation->present(
                $resolved[$index],
                $actionContext,
            );
        }

        return [
            'block_id' => $grid['block_id'],
            'content' => $content,
            'settings' => $settings,
            'items' => $items,
            'dynamic' => $dynamic,
            'notice' => $preview && $dynamic && $content['dynamic_source'] === 'videos' && $items === []
                ? 'ویدئوی قابل‌نمایش برای این منبع وجود ندارد؛ انتشار، دسته‌بندی و فعال بودن کتابخانه را بررسی کنید.' : null,
            'effective_columns' => $dynamic
                ? $this->effectiveColumns($settings)
                : 3,
            'grid_style' => $dynamic ? $this->gridStyle($settings) : '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function dynamicItems(array $content, array $settings): array
    {
        $source = $content['dynamic_source'];
        if ($source === 'videos') {
            return $this->videos->dynamicCards($content['video_category_id'], $settings['video_limit'], $content['video_sort']);
        }
        $limit = $settings['dynamic_rows'] * $this->effectiveColumns($settings);
        $overrides = collect($content['dynamic_button_overrides'])
            ->keyBy(fn (array $override): string => (string) $override['record_id']);

        if ($source === 'projects') {
            return Project::query()
                ->published()
                ->with('media')
                ->latest('published_at')
                ->take($limit)
                ->get()
                ->map(function (Project $project) use ($content, $overrides): array {
                    $override = $overrides->get((string) $project->getKey());

                    return [
                        'title' => $project->title,
                        'description' => $project->excerpt,
                        'image' => $project->featuredImageUrl('thumb'),
                        'icon' => null,
                        'button_label' => $override['button_label']
                            ?? $content['dynamic_button_label'],
                        'action' => ActionDestination::fromArray([
                            'type' => 'project',
                            'reference_id' => $project->getKey(),
                        ])->toArray(),
                    ];
                })
                ->all();
        }

        return Post::query()
            ->published()
            ->with('media')
            ->latest('published_at')
            ->take($limit)
            ->get()
            ->map(function (Post $post) use ($content, $overrides): array {
                $override = $overrides->get((string) $post->getKey());

                return [
                    'title' => $post->title,
                    'description' => $post->excerpt,
                    'image' => $post->featuredImageUrl('thumb'),
                    'icon' => null,
                    'button_label' => $override['button_label']
                        ?? $content['dynamic_button_label'],
                    'action' => ActionDestination::fromArray([
                        'type' => 'custom_url',
                        'value' => route('blog.show', $post->slug),
                    ])->toArray(),
                ];
            })
            ->all();
    }

    private function effectiveColumns(array $settings): int
    {
        $gap = 16;
        $widthLimited = max(1, (int) floor(
            ($settings['dynamic_grid_width'] + $gap)
            / ($settings['dynamic_item_width'] + $gap),
        ));

        return max(1, min($settings['dynamic_columns'], $widthLimited));
    }

    private function gridStyle(array $settings): string
    {
        return sprintf(
            '--feature-grid-width: %dpx; --feature-grid-item-width: %dpx; --feature-grid-columns: %d;',
            $settings['dynamic_grid_width'],
            $settings['dynamic_item_width'],
            $this->effectiveColumns($settings),
        );
    }
}
