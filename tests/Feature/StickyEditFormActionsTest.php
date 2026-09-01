<?php

namespace Tests\Feature;

use App\Filament\Resources\Concerns\HasStickyFormActions;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Form;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StickyEditFormActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_form_and_template_editors_enable_filaments_native_sticky_actions(): void
    {
        foreach ([EditPage::class, EditForm::class, EditTemplate::class] as $editor) {
            $this->assertContains(HasStickyFormActions::class, class_uses_recursive($editor));
        }

        $this->assertTrue(app(EditPage::class)->areFormActionsSticky());
        $this->assertTrue(app(EditForm::class)->areFormActionsSticky());
        $this->assertTrue(app(EditTemplate::class)->areFormActionsSticky());
        $this->assertSame(
            'fi-page-editor-locked-scroll fi-always-sticky-form-actions',
            app(EditPage::class)->getExtraBodyAttributes()['class'],
        );
        $this->assertSame(
            'fi-always-sticky-form-actions',
            app(EditForm::class)->getExtraBodyAttributes()['class'],
        );
        $this->assertSame(
            'fi-always-sticky-form-actions',
            app(EditTemplate::class)->getExtraBodyAttributes()['class'],
        );
    }

    public function test_filament_native_component_owns_the_sticky_responsive_presentation(): void
    {
        $actions = file_get_contents(base_path('vendor/filament/filament/resources/views/components/form/actions.blade.php'));

        $this->assertStringContainsString('$this->areFormActionsSticky()', $actions);
        $this->assertStringContainsString("'fi-sticky sticky bottom-0", $actions);
        $this->assertStringContainsString('md:bottom-4', $actions);
        $this->assertStringContainsString('md:rounded-xl', $actions);
    }

    public function test_form_and_template_edit_html_include_filaments_native_sticky_binding(): void
    {
        $this->actingAs(User::factory()->create());
        $form = Form::query()->create([
            'name' => 'Sticky form',
            'slug' => 'sticky-form',
            'status' => 'draft',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => []],
            'settings' => [],
        ]);
        $template = Template::query()->create([
            'title' => 'Sticky template',
            'slug' => 'sticky-template',
            'type' => 'page',
            'status' => 'draft',
            'blocks' => [],
            'conditions' => [],
            'is_default' => false,
            'priority' => 0,
        ]);

        foreach ([[EditForm::class, $form], [EditTemplate::class, $template]] as [$editor, $record]) {
            $html = Livewire::test($editor, ['record' => $record->getRouteKey()])->html();

            $this->assertStringContainsString('fi-form-actions', $html);
            $this->assertStringContainsString('fi-sticky sticky bottom-0', $html);
            $this->assertStringContainsString('fi-resource-edit-record-page', $html);
        }
    }

    public function test_always_sticky_css_does_not_depend_on_filaments_scroll_threshold(): void
    {
        $theme = file_get_contents(resource_path('views/filament/theme.blade.php'));

        $this->assertStringContainsString('.fi-resource-crm-forms.fi-resource-edit-record-page', $theme);
        $this->assertStringContainsString('.fi-resource-templates.fi-resource-edit-record-page', $theme);
        $this->assertStringContainsString('position: sticky !important;', $theme);
        $this->assertStringContainsString('bottom: 0 !important;', $theme);
        $this->assertStringContainsString('visibility: visible !important;', $theme);
    }
}
