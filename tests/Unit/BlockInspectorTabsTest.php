<?php

namespace Tests\Unit;

use App\Filament\Forms\Components\BlockInspectorTabs;
use PHPUnit\Framework\TestCase;

class BlockInspectorTabsTest extends TestCase
{
    public function test_content_driving_root_template_selector_is_grouped_with_content(): void
    {
        $this->assertSame(
            BlockInspectorTabs::CONTENT,
            BlockInspectorTabs::groupForStateName('template'),
        );
    }

    public function test_presentation_only_variant_layout_and_nested_template_fields_stay_in_design(): void
    {
        foreach (['variant', 'layout', 'settings.template', 'settings.layout'] as $name) {
            $this->assertSame(
                BlockInspectorTabs::DESIGN,
                BlockInspectorTabs::groupForStateName($name),
            );
        }
    }
}
