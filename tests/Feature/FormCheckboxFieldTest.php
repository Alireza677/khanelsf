<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormCheckboxFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_palette_creates_checkbox_with_reusable_single_input_choice_editor(): void
    {
        $this->actingAs(User::factory()->create());
        $component = Livewire::test(CreateForm::class)
            ->assertSee('فیلدهای انتخابی')
            ->assertSee('انتخاب چندگانه')
            ->callFormComponentAction('schema.fields', 'add', arguments: ['fieldType' => 'checkbox'])
            ->assertHasNoFormComponentActionErrors();
        $fields = $component->get('data')['schema']['fields'];
        $fieldKey = array_key_last($fields);
        $optionsPath = "schema.fields.{$fieldKey}.options";
        $initialOptionCount = count($fields[$fieldKey]['options'] ?? []);

        $component
            ->callFormComponentAction($optionsPath, 'add')
            ->callFormComponentAction($optionsPath, 'add')
            ->assertHasNoFormComponentActionErrors()
            ->assertSee('عنوان گزینه')
            ->assertDontSee('مقدار گزینه');

        $options = $component->get('data')['schema']['fields'][$fieldKey]['options'];
        $this->assertCount($initialOptionCount + 2, $options);
        $this->assertCount(count($options), array_unique(array_column(array_values($options), 'value')));
        foreach ($options as $option) {
            $this->assertMatchesRegularExpression(
                '/^option_[0-7][0-9a-hjkmnp-tv-z]{25}$/',
                $option['value'],
            );
        }
    }

    public function test_frontend_allows_multiple_checked_options_and_stores_array_with_labels(): void
    {
        $form = $this->checkboxForm(required: true);
        $url = route('forms.show', $form->slug);
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'name="services[]"'));
        $this->assertSame(3, substr_count($html, 'type="checkbox"'));

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'services' => ['design', 'execution'],
        ])->assertRedirect($url)->assertSessionHasNoErrors();

        $submission = FormSubmission::query()->sole();
        $this->assertSame(['design', 'execution'], $submission->payload['services']);
        $this->assertSame(
            'طراحی سازه، اجرای LSF',
            data_get($submission->payload, '_answer_snapshot.0.display_value'),
        );
    }

    public function test_checkbox_renderer_uses_adaptive_spans_without_changing_input_contract(): void
    {
        $form = $this->checkboxForm(required: false);
        $schema = $form->schema;
        $schema['fields'][0]['options'] = [
            ['value' => 'short_value', 'label' => str_repeat('آ', 25)],
            ['value' => 'medium_value', 'label' => str_repeat('ب', 26)],
            ['value' => 'long_value', 'label' => str_repeat('پ', 51)],
        ];
        $form->update(['schema' => $schema]);

        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();

        $this->assertStringContainsString('form-adaptive-choice-grid', $html);
        $this->assertStringContainsString('choice-grid-item--short', $html);
        $this->assertStringContainsString('choice-grid-item--medium', $html);
        $this->assertStringContainsString('choice-grid-item--long', $html);
        $this->assertSame(3, substr_count($html, 'name="services[]"'));
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('value="medium_value"', $html);
    }

    public function test_required_optional_allowlist_and_duplicate_validation_contract(): void
    {
        $required = $this->checkboxForm(required: true);
        $requiredUrl = route('forms.show', $required->slug);

        $this->from($requiredUrl)
            ->post(route('forms.submit', $required->slug), [])
            ->assertRedirect($requiredUrl)
            ->assertSessionHasErrors('services');

        $this->from($requiredUrl)
            ->post(route('forms.submit', $required->slug), ['services' => ['design']])
            ->assertRedirect($requiredUrl)
            ->assertSessionHasNoErrors();

        $this->from($requiredUrl)
            ->post(route('forms.submit', $required->slug), ['services' => ['design', 'tampered']])
            ->assertRedirect($requiredUrl)
            ->assertSessionHasErrors('services.1');

        $this->from($requiredUrl)
            ->post(route('forms.submit', $required->slug), ['services' => ['design', 'design']])
            ->assertRedirect($requiredUrl)
            ->assertSessionHasErrors('services.0');

        $optional = $this->checkboxForm(required: false, slug: 'optional-checkbox-form');
        $this->from(route('forms.show', $optional->slug))
            ->post(route('forms.submit', $optional->slug), [])
            ->assertSessionHasNoErrors();
    }

    public function test_validation_failure_restores_every_valid_old_selection(): void
    {
        $form = $this->checkboxForm(required: true);
        $url = route('forms.show', $form->slug);

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'services' => ['design', 'execution', 'tampered'],
        ])->assertRedirect($url);

        $html = $this->get($url)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="design"[^>]*checked/u', $html);
        $this->assertMatchesRegularExpression('/value="execution"[^>]*checked/u', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
    }

    public function test_adaptive_choice_css_uses_one_two_and_three_column_breakpoints(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.form-adaptive-choice-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1fr\)/s', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 768px\)[\s\S]*?\.form-adaptive-choice-grid\s*\{[^}]*repeat\(2, minmax\(0, 1fr\)\)/', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 1024px\)[\s\S]*?\.form-adaptive-choice-grid\s*\{[^}]*repeat\(3, minmax\(0, 1fr\)\)/', $css);
        $this->assertMatchesRegularExpression('/\.choice-grid-item--long\s*\{[^}]*grid-column:\s*span 3/s', $css);
    }

    private function checkboxForm(bool $required, string $slug = 'checkbox-form'): Form
    {
        return Form::query()->create([
            'name' => 'فرم انتخاب چندگانه',
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [[
                'key' => 'services',
                'label' => 'خدمات موردنیاز',
                'type' => 'checkbox',
                'required' => $required,
                'options' => [
                    ['value' => 'design', 'label' => 'طراحی سازه'],
                    ['value' => 'materials', 'label' => 'تأمین مصالح'],
                    ['value' => 'execution', 'label' => 'اجرای LSF'],
                ],
            ]]],
            'settings' => [],
        ]);
    }
}
