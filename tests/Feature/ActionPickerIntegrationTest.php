<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPickerService;
use App\Models\Form;
use App\Models\Page;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\ActionPickerLifecycleComponent;
use Tests\TestCase;

class ActionPickerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_entity_and_form_search_use_published_target_specific_sources(): void
    {
        $page = Page::factory()->published()->create([
            'title' => 'Picker Page',
            'slug' => 'picker-page',
        ]);
        Page::factory()->draft()->create(['title' => 'Picker Draft Page']);
        $project = Project::factory()->published()->create(['title' => 'Picker Project']);
        $product = Product::factory()->published()->create([
            'title' => 'Picker Product',
            'has_stock' => false,
            'stock_status' => 'out_of_stock',
        ]);
        $service = $this->service('Picker Service', 'picker-service', Service::STATUS_ACTIVE);
        $form = $this->form('Picker Form', 'picker-form', 'published');
        $this->form('Picker Draft Form', 'picker-draft-form', 'draft');
        $picker = app(ActionPickerService::class);

        $pageOptions = $picker->searchOptions('page', 'Picker Page');

        $this->assertSame([$page->id], array_keys($pageOptions));
        $this->assertSame('Picker Page — برگه — /picker-page', $pageOptions[$page->id]);
        $this->assertSame([$project->id], array_keys($picker->searchOptions('project', 'Picker Project')));
        $this->assertSame([$product->id], array_keys($picker->searchOptions('product', 'Picker Product')));
        $this->assertSame([$service->id], array_keys($picker->searchOptions('service', 'Picker Service')));
        $this->assertSame([$form->id], array_keys($picker->searchOptions('form', 'Picker Form')));
        $this->assertSame([], $picker->searchOptions('page', 'x'));
    }

    public function test_selected_labels_are_resolved_by_reference_and_unavailable_targets_fail_closed(): void
    {
        $page = Page::factory()->published()->create(['title' => 'Selected Page']);
        $draft = Page::factory()->draft()->create(['title' => 'Selected Draft']);
        $form = $this->form('Selected Form', 'selected-form', 'published');
        $draftForm = $this->form('Selected Draft Form', 'selected-draft-form', 'draft');
        $picker = app(ActionPickerService::class);

        $this->assertSame('Selected Page', $picker->selectedOptionLabel('page', $page->id));
        $this->assertSame('مقصد در دسترس نیست', $picker->selectedOptionLabel('page', $draft->id));
        $this->assertSame('مقصد در دسترس نیست', $picker->selectedOptionLabel('page', 999999));
        $this->assertSame('Selected Form', $picker->selectedOptionLabel('form', $form->id));
        $this->assertSame('فرم در دسترس نیست', $picker->selectedOptionLabel('form', $draftForm->id));

        $this->assertSame(
            'مقصد انتخاب‌شده در دسترس نیست.',
            $picker->validationMessage(
                ['type' => 'page', 'reference_id' => $draft->id],
                true,
                array_keys($picker->typeOptions()),
            ),
        );
        $this->assertSame(
            'فرم انتخاب‌شده در دسترس نیست.',
            $picker->validationMessage(
                ['type' => 'form', 'reference_id' => $draftForm->id, 'display' => 'modal'],
                true,
                array_keys($picker->typeOptions()),
            ),
        );
    }

    public function test_disabled_modules_return_no_picker_results(): void
    {
        Project::factory()->published()->create(['title' => 'Disabled Picker Project']);
        Product::factory()->published()->create(['title' => 'Disabled Picker Product']);
        app(SettingsService::class)->set('projects_enabled', false, 'projects', 'boolean');
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        $picker = app(ActionPickerService::class);

        $this->assertSame([], $picker->searchOptions('project', 'Disabled Picker'));
        $this->assertSame([], $picker->searchOptions('product', 'Disabled Picker'));
    }

    public function test_livewire_component_hydrates_switches_and_saves_only_canonical_state(): void
    {
        $page = Page::factory()->published()->create(['title' => 'Lifecycle Page']);

        $component = Livewire::test(ActionPickerLifecycleComponent::class, [
            'action' => [
                'schema_version' => 1,
                'type' => 'page',
                'reference_id' => $page->id,
                'open_in_new_tab' => true,
            ],
            'required' => true,
        ]);

        $this->assertSame($page->id, $component->get('data.action.reference_id'));

        $component
            ->set('data.action.type', 'custom_url')
            ->assertSet('data.action.reference_id', null)
            ->assertSet('data.action.display', null)
            ->assertSet('data.action.open_in_new_tab', false)
            ->set('data.action.value', '/contact')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([
            'schema_version' => 1,
            'type' => 'custom_url',
            'value' => '/contact',
            'open_in_new_tab' => false,
        ], $component->get('saved.action'));

        $component
            ->set('data.action.type', 'form')
            ->assertSet('data.action.value', null)
            ->assertSet('data.action.reference_id', null)
            ->assertSet('data.action.display', 'modal')
            ->assertSet('data.action.open_in_new_tab', false);
    }

    public function test_livewire_required_and_unsafe_states_show_persian_validation_errors(): void
    {
        Livewire::test(ActionPickerLifecycleComponent::class, [
            'action' => null,
            'required' => true,
        ])
            ->call('save')
            ->assertHasErrors(['data.action.type' => 'required']);

        $unsafe = Livewire::test(ActionPickerLifecycleComponent::class, [
            'action' => [
                'type' => 'custom_url',
                'value' => 'javascript:alert(1)',
            ],
            'required' => true,
        ])->call('save');

        $unsafe->assertHasErrors(['data.action.type']);
        $this->assertStringContainsString(
            'لینک واردشده معتبر نیست.',
            $unsafe->html(),
        );
    }

    public function test_livewire_custom_url_accepts_and_explains_temporary_placeholder(): void
    {
        $component = Livewire::test(ActionPickerLifecycleComponent::class, [
            'action' => [
                'type' => 'custom_url',
                'value' => ' # ',
            ],
            'required' => true,
        ]);

        $this->assertStringContainsString(
            'برای ایجاد موقت دکمه بدون مقصد، می‌توانید فقط # وارد کنید.',
            $component->html(),
        );

        $component
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('#', $component->get('saved.action.value'));
    }

    private function service(string $name, string $slug, string $status): Service
    {
        return Service::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    private function form(string $name, string $slug, string $status): Form
    {
        return Form::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
        ]);
    }
}
