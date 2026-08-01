<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectMetric;
use App\Models\Service;
use App\Models\Template;
use App\Services\ProjectTemplateContextBuilder;
use Tests\TestCase;

class ProjectProductionBlockBehaviorTest extends TestCase
{
    public function test_empty_project_blocks_render_no_empty_section(): void
    {
        $project = new Project;
        $project->setRelation('metrics', collect());
        $project->setRelation('relatedServices', collect());

        foreach ([
            'project_overview',
            'project_metrics',
            'project_services',
            'project_story',
        ] as $view) {
            $html = $this->render($view, $project);

            $this->assertStringNotContainsString('<section', $html, $view);
        }
    }

    public function test_overview_uses_persian_labels_and_preserves_legacy_attributes(): void
    {
        $project = new Project([
            'client_name' => 'کارفرمای نمونه',
            'attributes' => [
                ['label' => 'زیربنا', 'value' => '۱۲۰۰ مترمربع'],
            ],
        ]);

        $html = $this->render('project_overview', $project);

        $this->assertStringContainsString('کارفرما', $html);
        $this->assertStringContainsString('کارفرمای نمونه', $html);
        $this->assertStringContainsString('زیربنا', $html);
        $this->assertStringContainsString('۱۲۰۰ مترمربع', $html);
        $this->assertStringNotContainsString('Client', $html);
    }

    public function test_metrics_render_description_and_hide_when_empty(): void
    {
        $project = new Project;
        $project->setRelation('metrics', collect([
            new ProjectMetric([
                'label' => 'کاهش مصرف',
                'value' => '۳۰',
                'suffix' => '٪',
                'description' => 'نسبت به خط مبنا',
            ]),
        ]));

        $html = $this->render('project_metrics', $project);

        $this->assertStringContainsString('کاهش مصرف', $html);
        $this->assertStringContainsString('نسبت به خط مبنا', $html);
    }

    public function test_services_use_active_relation_then_legacy_and_never_lazy_load(): void
    {
        $project = new Project([
            'services' => [['name' => 'خدمت قدیمی']],
        ]);
        $project->setRelation('relatedServices', collect([
            new Service(['name' => 'خدمت غیرفعال', 'status' => 'inactive']),
        ]));

        $legacyHtml = $this->render('project_services', $project);

        $this->assertStringContainsString('خدمت قدیمی', $legacyHtml);
        $this->assertStringNotContainsString('خدمت غیرفعال', $legacyHtml);
        $this->assertTrue($project->relationLoaded('relatedServices'));

        $project->setRelation('relatedServices', collect([
            new Service(['name' => 'خدمت فعال', 'status' => 'active']),
        ]));

        $relationHtml = $this->render('project_services', $project);

        $this->assertStringContainsString('خدمت فعال', $relationHtml);
        $this->assertStringNotContainsString('خدمت قدیمی', $relationHtml);
    }

    public function test_story_falls_back_to_legacy_content_only_without_structured_story(): void
    {
        $legacy = new Project(['content' => '<p>روایت قدیمی پروژه</p>']);

        $this->assertStringContainsString(
            '<p>روایت قدیمی پروژه</p>',
            $this->render('project_story', $legacy),
        );

        $structured = new Project([
            'content' => '<p>روایت قدیمی پروژه</p>',
            'challenge' => 'چالش ساختاریافته',
        ]);
        $html = $this->render('project_story', $structured);

        $this->assertStringContainsString('چالش ساختاریافته', $html);
        $this->assertStringNotContainsString('روایت قدیمی پروژه', $html);
    }

    public function test_related_limit_is_derived_from_selected_template(): void
    {
        $builder = app(ProjectTemplateContextBuilder::class);
        $template = new Template([
            'blocks' => [
                ['type' => 'project_header', 'data' => []],
                ['type' => 'related_projects', 'data' => ['settings' => ['limit' => 6]]],
            ],
        ]);

        $this->assertSame(6, $builder->relatedLimit($template));
        $this->assertSame(3, $builder->relatedLimit(null));

        $withoutRelated = new Template([
            'blocks' => [['type' => 'project_header', 'data' => []]],
        ]);

        $this->assertSame(0, $builder->relatedLimit($withoutRelated));
    }

    private function render(string $view, Project $project): string
    {
        return view("partials.blocks.{$view}", [
            'data' => [],
            'context' => [
                'kind' => 'single',
                'type' => 'project',
                'model' => $project,
            ],
        ])->render();
    }
}
