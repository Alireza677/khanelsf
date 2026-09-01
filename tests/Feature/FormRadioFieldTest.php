<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormRadioFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_radio_renders_as_standard_single_selection_in_frontend_and_modal_preview(): void
    {
        $form = $this->radioForm();

        $frontend = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();
        $preview = $this->post(route('forms.modal', $form->slug))->assertOk()->getContent();

        foreach ([$frontend, $preview] as $html) {
            $this->assertStringContainsString('form-radio-group', $html);
            $this->assertSame(2, substr_count($html, 'name="contact_method"'));
            $this->assertSame(2, substr_count($html, 'type="radio"'));
            $this->assertStringContainsString('value="phone"', $html);
            $this->assertStringContainsString('value="email"', $html);
            $this->assertStringContainsString('تماس تلفنی', $html);
            $this->assertStringContainsString('ایمیل', $html);
        }
    }

    public function test_radio_uses_the_shared_adaptive_grid_without_changing_input_contract(): void
    {
        $form = $this->radioForm();
        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();

        $this->assertStringContainsString('form-adaptive-choice-grid form-radio-options', $html);
        $this->assertStringContainsString('form-adaptive-choice form-radio-option choice-grid-item--short', $html);
        $this->assertSame(2, substr_count($html, 'name="contact_method"'));
        $this->assertSame(2, substr_count($html, 'type="radio"'));
        $this->assertStringContainsString('value="phone"', $html);
    }

    public function test_required_radio_rejects_empty_and_unknown_values_and_restores_old_input(): void
    {
        $form = $this->radioForm();
        $showUrl = route('forms.show', $form->slug);

        $this->from($showUrl)
            ->post(route('forms.submit', $form->slug), [])
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors('contact_method');

        $this->from($showUrl)
            ->post(route('forms.submit', $form->slug), ['contact_method' => 'unknown'])
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors('contact_method');

        $invalid = $this->get($showUrl)->assertOk()->getContent();
        $this->assertStringContainsString('aria-invalid="true"', $invalid);

        $this->from($showUrl)
            ->post(route('forms.submit', $form->slug), ['contact_method' => 'email'])
            ->assertRedirect($showUrl);

        $oldInput = $this->withSession(['_old_input' => ['contact_method' => 'phone']])
            ->get($showUrl)
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/value="phone"[^>]*checked/u', $oldInput);
    }

    public function test_radio_submission_stores_single_value_and_human_readable_snapshot(): void
    {
        $form = $this->radioForm();

        $this->post(route('forms.submit', $form->slug), [
            'contact_method' => 'phone',
        ])->assertRedirect();

        $submission = FormSubmission::query()->sole();
        $this->assertSame('phone', $submission->payload['contact_method']);
        $this->assertSame(
            'تماس تلفنی',
            data_get($submission->payload, '_answer_snapshot.0.display_value'),
        );
    }

    public function test_legacy_radio_card_still_normalizes_and_renders_but_is_not_a_creatable_type(): void
    {
        $form = $this->radioForm('radio_card');
        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();

        $this->assertStringContainsString('class="form-choice-card"', $html);
        $this->assertStringContainsString('type="radio"', $html);
        $this->assertArrayNotHasKey('radio_card', \App\Filament\Resources\FormResource::fieldTypeLabels());
        $this->assertContains('radio_card', \App\Services\FormSchema::supportedTypes());
    }

    public function test_standard_radio_options_do_not_use_card_borders(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match('/\.form-adaptive-choice\s*\{(?<rules>[^}]*)\}/s', $css, $match);

        $this->assertNotEmpty($match['rules'] ?? null);
        $this->assertStringNotContainsString('border:', $match['rules']);
        $this->assertStringNotContainsString('.form-radio-option:has(input:checked)', $css);
    }

    private function radioForm(string $type = 'radio'): Form
    {
        return Form::query()->create([
            'name' => 'فرم رادیویی',
            'slug' => 'radio-form-'.$type,
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [[
                'key' => 'contact_method',
                'label' => 'روش تماس',
                'type' => $type,
                'required' => true,
                'options' => [
                    ['value' => 'phone', 'label' => 'تماس تلفنی'],
                    ['value' => 'email', 'label' => 'ایمیل'],
                ],
            ]]],
            'settings' => [],
        ]);
    }
}
