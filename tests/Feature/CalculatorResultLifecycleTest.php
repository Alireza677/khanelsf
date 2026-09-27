<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculatorResultLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_submit_from_an_action_keeps_its_instance_and_opens_current_result(): void
    {
        $form = $this->calculator();
        $url = route('forms.show', $form->slug);
        $token = 'action-first-visit';
        $this->post(route('forms.context', $form->slug), ['_form_instance' => $token])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('name="_form_instance" value="'.$token.'"', false);

        $this->from($url)->post(route('forms.submit', $form->slug), [
            '_form_instance' => $token, '_display_mode' => 'page', 'choice' => 'a', 'name' => 'Current visitor',
        ])->assertRedirect($url)
            ->assertSessionHas("calculator_result_instances.{$token}.calculation_result.result", 'Recommendation A');

        $this->assertDatabaseCount('form_submissions', 1);
        $this->get($url)->assertOk()->assertViewHas('instanceToken', $token)
            ->assertSee('data-calculator-result-modal', false)
            ->assertSee('Recommendation A')
            ->assertSee('value="Current visitor"', false)
            ->assertDontSee('class="form-page__result"', false);
        $this->get($url)->assertOk()->assertDontSee('data-calculator-result-modal', false);
    }

    public function test_direct_first_submit_renders_one_modal_and_consumes_its_flash(): void
    {
        $form = $this->calculator();
        $url = route('forms.show', $form->slug);
        $this->get($url)->assertOk()->assertDontSee('data-calculator-result-modal', false);
        $this->from($url)->post(route('forms.submit', $form->slug), ['choice' => 'a', '_display_mode' => 'page'])
            ->assertRedirect($url)->assertSessionHas('calculator_result_state.submission_id', FormSubmission::query()->sole()->id);
        $html = $this->get($url)->assertOk()->assertSee('role="dialog"', false)
            ->assertSessionMissing('calculator_result_state')->assertSessionMissing('calculator_result_redirect')->getContent();
        $this->assertSame(1, substr_count($html, 'data-calculator-result-modal'));
        $this->get($url)->assertOk()->assertDontSee('data-calculator-result-modal', false);
    }

    public function test_new_submission_uses_its_own_answers_even_with_an_unconsumed_old_result(): void
    {
        $form = $this->calculator();
        $url = route('forms.show', $form->slug);
        $this->from($url)->post(route('forms.submit', $form->slug), ['choice' => 'a', '_display_mode' => 'page'])
            ->assertSessionHas('calculator_result_state.calculation_result.result', 'Recommendation A');
        // Intentionally leave the first response unconsumed and submit different answers.
        $this->from($url)->post(route('forms.submit', $form->slug), ['choice' => 'b', '_display_mode' => 'page'])
            ->assertSessionHas('calculator_result_state.calculation_result.result', 'Recommendation B')
            ->assertSessionHas('calculator_result_state.submission_id', FormSubmission::query()->latest('id')->first()->id);
        $this->assertDatabaseCount('form_submissions', 2);
        $this->assertSame('b', FormSubmission::query()->latest('id')->first()->calculation_result['answers']['choice']);
        $this->get($url)->assertOk()->assertSee('<strong>Recommendation B</strong>', false);
    }

    public function test_invalid_attempt_discards_stale_results_instead_of_auto_opening_them(): void
    {
        $form = $this->calculator();
        $url = route('forms.show', $form->slug);
        $this->from($url)->post(route('forms.submit', $form->slug), ['choice' => 'a', '_form_instance' => 'older-action'])
            ->assertSessionHas('calculator_result_instances.older-action');
        $this->from($url)->post(route('forms.submit', $form->slug), ['choice' => 'invalid', '_display_mode' => 'page'])
            ->assertSessionHasErrors('choice')->assertSessionMissing('calculator_result_instances.older-action');
        $this->get($url)->assertOk()->assertDontSee('data-calculator-result-modal', false);
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_calculator_modal_submission_also_opens_its_result_on_first_redirect(): void
    {
        $form = $this->calculator();
        $token = 'modal-action';
        $this->post(route('forms.modal', $form->slug), ['_form_instance' => $token])->assertOk();
        $this->post(route('forms.submit', $form->slug), ['choice' => 'b', '_display_mode' => 'modal', '_form_instance' => $token])
            ->assertRedirect(route('forms.show', $form->slug));
        $this->get(route('forms.show', $form->slug))->assertOk()->assertViewHas('instanceToken', $token)
            ->assertSee('data-calculator-result-modal', false)->assertSee('<strong>Recommendation B</strong>', false);
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_validation_after_a_success_still_belongs_to_the_submitting_instance(): void
    {
        $form = $this->calculator();
        $url = route('forms.show', $form->slug);
        $token = 'retained-instance';
        $this->from($url)->post(route('forms.submit', $form->slug), ['choice' => 'a', '_form_instance' => $token]);
        $this->get($url)->assertOk()->assertSee('data-calculator-result-modal', false);
        $this->from($url)->post(route('forms.submit', $form->slug), [
            'choice' => 'invalid', 'name' => 'Corrected visitor', '_form_instance' => $token,
        ])->assertSessionHasErrors(['choice'], null, 'form_'.substr(hash('sha256', $token), 0, 24));
        $this->get($url)->assertOk()->assertViewHas('instanceToken', $token)
            ->assertSee('aria-invalid="true"', false)->assertSee('value="Corrected visitor"', false)
            ->assertDontSee('data-calculator-result-modal', false);
        $this->assertDatabaseCount('form_submissions', 1);
    }

    private function calculator(): Form
    {
        return Form::query()->create([
            'name' => 'Result lifecycle', 'slug' => 'result-lifecycle', 'status' => 'published',
            'type' => 'calculator', 'calculator_identifier' => 'result_lifecycle', 'schema_version' => 2,
            'schema' => [
                'fields' => [
                    ['key' => 'question', 'type' => 'page', 'label' => 'Question'],
                    ['key' => 'choice', 'type' => 'radio', 'label' => 'Choice', 'required' => true, 'options' => [
                        ['value' => 'a', 'label' => 'A', 'scores' => ['method_a' => 10]],
                        ['value' => 'b', 'label' => 'B', 'scores' => ['method_b' => 10]],
                    ]],
                    ['key' => 'contact', 'type' => 'page', 'label' => 'Contact'],
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                ],
                'calculator' => ['recommendations' => ['method_a' => 'Recommendation A', 'method_b' => 'Recommendation B']],
            ],
        ]);
    }
}
