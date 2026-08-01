<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Template;
use App\Services\TemplateService;
use ReflectionMethod;
use Tests\TestCase;

class TemplateSelectionRuleTest extends TestCase
{
    public function test_project_template_specificity_is_specific_then_category_then_default(): void
    {
        $project = new Project([
            'project_category_id' => 20,
        ]);
        $project->id = 10;

        $this->assertSame(3, $this->specificity($this->template(
            conditions: ['type' => 'specific_item', 'item_id' => 10],
            isDefault: false,
        ), $project));
        $this->assertSame(2, $this->specificity($this->template(
            conditions: ['type' => 'category', 'category_id' => 20],
            isDefault: false,
        ), $project));
        $this->assertSame(1, $this->specificity($this->template(
            conditions: ['type' => 'all'],
            isDefault: true,
        ), $project));
        $this->assertSame(0, $this->specificity($this->template(
            conditions: ['type' => 'all'],
            isDefault: false,
        ), $project));
    }

    private function template(array $conditions, bool $isDefault): Template
    {
        return new Template([
            'type' => 'project_single',
            'conditions' => $conditions,
            'is_default' => $isDefault,
        ]);
    }

    private function specificity(Template $template, Project $project): int
    {
        $method = new ReflectionMethod(TemplateService::class, 'specificityFor');

        return $method->invoke(new TemplateService, $template, $project);
    }
}
