<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\User;
use App\Support\FormSubmitConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormSubmitConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_and_disabled_forms_submit_without_confirmation(): void
    {
        $legacy = $this->form('legacy-confirmation', []);
        $disabled = $this->form('disabled-confirmation', ['submit_confirmation_enabled' => false]);

        $this->get(route('forms.show', $legacy->slug))
            ->assertOk()
            ->assertDontSee('data-submit-confirmation', false);

        $this->get(route('forms.show', $disabled->slug))
            ->assertOk()
            ->assertDontSee('data-submit-confirmation', false);

        $this->post(route('forms.submit', $legacy->slug), [])->assertSessionHasNoErrors();
        $this->post(route('forms.submit', $disabled->slug), [])->assertSessionHasNoErrors();

        $this->assertSame(2, $legacy->submissions()->count() + $disabled->submissions()->count());
    }

    public function test_enabled_confirmation_renders_text_and_initially_disabled_submit(): void
    {
        $form = $this->confirmedForm();

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertSee('تأیید می‌کنم اطلاعات صحیح است')
            ->assertSee('data-submit-confirmation-input', false)
            ->assertSee('data-form-submit disabled', false);
    }

    public function test_backend_rejects_missing_confirmation_and_preserves_checked_old_input(): void
    {
        $form = $this->confirmedForm(requiredField: true);
        $url = route('forms.show', $form->slug);

        $this->from($url)
            ->post(route('forms.submit', $form->slug), [])
            ->assertRedirect($url)
            ->assertSessionHasErrors(FormSubmitConfirmation::INPUT_KEY);

        $this->assertDatabaseCount('form_submissions', 0);

        $this->from($url)
            ->post(route('forms.submit', $form->slug), [FormSubmitConfirmation::INPUT_KEY => '1'])
            ->assertRedirect($url)
            ->assertSessionHasErrors('name');

        $this->get($url)
            ->assertOk()
            ->assertSee('data-submit-confirmation-input', false)
            ->assertSee('checked', false);
    }

    public function test_confirmation_creates_submission_with_immutable_audit_snapshot(): void
    {
        $form = $this->confirmedForm();

        $this->post(route('forms.submit', $form->slug), [
            FormSubmitConfirmation::INPUT_KEY => '1',
        ])->assertSessionHasNoErrors();

        $submission = $form->submissions()->sole();
        $audit = $submission->payload[FormSubmitConfirmation::PAYLOAD_KEY];

        $this->assertTrue($audit['confirmed']);
        $this->assertSame('تأیید می‌کنم اطلاعات صحیح است', $audit['confirmation_text_snapshot']);
        $this->assertNotEmpty($audit['confirmed_at']);
        $this->assertArrayNotHasKey(FormSubmitConfirmation::INPUT_KEY, $submission->payload);
        $this->assertSame([], $submission->payload['_answer_snapshot']);

        $form->update(['settings' => [
            'submit_confirmation_enabled' => true,
            'submit_confirmation_text' => 'متن جدید مدیر',
        ]]);

        $this->assertSame(
            'تأیید می‌کنم اطلاعات صحیح است',
            $submission->fresh()->payload[FormSubmitConfirmation::PAYLOAD_KEY]['confirmation_text_snapshot'],
        );
    }

    public function test_admin_requires_confirmation_text_only_when_enabled(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form('admin-confirmation', []);

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->set('data.settings.submit_confirmation_enabled', true)
            ->set('data.settings.submit_confirmation_text', '')
            ->call('save')
            ->assertHasErrors(['data.settings.submit_confirmation_text' => 'required']);
    }

    public function test_shared_client_script_enables_and_disables_submit_on_change(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("checkbox.addEventListener('change', sync)", $source);
        $this->assertStringContainsString('button.disabled = ! checkbox.checked', $source);
        $this->assertStringContainsString('initFormSubmitConfirmations();', $source);
    }

    public function test_confirmation_checkbox_overrides_global_full_width_input_style(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.form-submit-confirmation input\s*\{[^}]*height:\s*1rem;[^}]*width:\s*1rem;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.form-submit-confirmation span\s*\{[^}]*flex:\s*1 1 auto;/s',
            $css,
        );
    }

    private function confirmedForm(bool $requiredField = false): Form
    {
        return $this->form('confirmed-form-'.($requiredField ? 'required' : 'simple'), [
            'submit_confirmation_enabled' => true,
            'submit_confirmation_text' => 'تأیید می‌کنم اطلاعات صحیح است',
        ], $requiredField ? [[
            'field_id' => '01J00000000000000000000001',
            'key' => 'name',
            'label' => 'نام',
            'type' => 'text',
            'required' => true,
            'layout' => ['span' => 12],
            'settings' => [],
        ]] : []);
    }

    private function form(string $slug, array $settings, array $fields = []): Form
    {
        return Form::query()->create([
            'name' => 'فرم تست',
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => Form::SCHEMA_VERSION,
            'schema' => ['fields' => $fields],
            'settings' => $settings,
        ]);
    }
}
