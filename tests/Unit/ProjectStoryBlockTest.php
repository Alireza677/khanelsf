<?php

namespace Tests\Unit;

use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Project\ProjectStoryBlock;
use App\Models\Project;
use Filament\Forms\Components\Component;
use Tests\TestCase;

class ProjectStoryBlockTest extends TestCase
{
    public function test_contract_and_schema_follow_project_block_conventions(): void
    {
        $block = app(ProjectStoryBlock::class);

        $this->assertSame('project_story', $block->key());
        $this->assertMatchesRegularExpression('/^project_[a-z0-9_]+$/', $block->key());
        $this->assertContains('project_context', $block->capabilities());
        $this->assertContains('dynamic_data', $block->capabilities());
        $this->assertContains('case_study_narrative', $block->capabilities());

        $names = $this->componentNames($block->filamentSchema(HeroBlock::CONTEXT_TEMPLATE));

        $this->assertContains('content.title', $names);
        foreach (['challenge', 'solution', 'results_summary', 'client_quote'] as $section) {
            $this->assertContains("content.headings.{$section}", $names);
            $this->assertContains("settings.show_{$section}", $names);
        }
    }

    public function test_normalizer_is_canonical_idempotent_and_does_not_snapshot_project_data(): void
    {
        $block = app(ProjectStoryBlock::class);
        $once = $block->normalize([
            'content' => [
                'title' => 'داستان پروژه',
                'challenge' => 'این مقدار نباید snapshot شود',
                'headings' => [
                    'challenge' => 'مسئله',
                    'solution' => '',
                ],
            ],
            'settings' => [
                'show_challenge' => false,
                'show_solution' => true,
                'show_results_summary' => 'false',
            ],
            'unknown' => 'discard me',
        ]);

        $this->assertSame([
            'title' => 'داستان پروژه',
            'headings' => [
                'challenge' => 'مسئله',
                'solution' => 'راهکار اجراشده',
                'results_summary' => 'خلاصه نتایج',
                'client_quote' => 'نظر کارفرما',
            ],
        ], $once['content']);
        $this->assertSame([
            'show_challenge' => false,
            'show_solution' => true,
            'show_results_summary' => true,
            'show_client_quote' => true,
        ], $once['settings']);
        $this->assertArrayNotHasKey('challenge', $once['content']);
        $this->assertArrayNotHasKey('unknown', $once);
        $this->assertSame($once, $block->normalize($once));
    }

    public function test_renderer_reads_project_attributes_and_respects_visibility_without_queries(): void
    {
        $project = new Project([
            'challenge' => 'متن چالش',
            'solution' => 'متن راهکار پنهان',
            'results_summary' => 'متن نتایج',
            'client_quote' => 'متن نظر کارفرما',
        ]);

        $html = view('partials.blocks.project_story', [
            'data' => [
                'content' => [
                    'title' => 'روایت سفارشی',
                    'headings' => ['challenge' => 'چالش سفارشی'],
                ],
                'settings' => ['show_solution' => false],
            ],
            'context' => ['type' => 'project', 'model' => $project],
        ])->render();

        $this->assertStringContainsString('روایت سفارشی', $html);
        $this->assertStringContainsString('چالش سفارشی', $html);
        $this->assertStringContainsString('متن چالش', $html);
        $this->assertStringContainsString('متن نتایج', $html);
        $this->assertStringContainsString('متن نظر کارفرما', $html);
        $this->assertStringNotContainsString('متن راهکار پنهان', $html);
    }

    /**
     * @param  array<Component>  $components
     * @return array<string>
     */
    private function componentNames(array $components): array
    {
        return collect($components)
            ->flatMap(function (Component $component): array {
                $names = method_exists($component, 'getName') ? [$component->getName()] : [];
                $children = method_exists($component, 'getChildComponents')
                    ? $component->getChildComponents()
                    : [];

                return [...$names, ...$this->componentNames($children)];
            })
            ->filter()
            ->values()
            ->all();
    }
}
