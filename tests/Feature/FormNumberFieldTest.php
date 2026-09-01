<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormNumberFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_number_is_available_in_standard_palette_with_presentation_defaults(): void
    {
        $this->actingAs(User::factory()->create());
        $component = Livewire::test(CreateForm::class)
            ->assertSee('فیلدهای استاندارد')
            ->assertSee('عدد')
            ->callFormComponentAction('schema.fields', 'add', arguments: ['fieldType' => 'number'])
            ->assertHasNoFormComponentActionErrors();
        $field = collect($component->get('data')['schema']['fields'])->last();

        $this->assertSame('number', $field['type']);
        $this->assertSame([
            'thousands_separator' => false,
            'allow_decimals' => false,
            'decimal_places' => 2,
        ], $field['settings']);
    }

    public function test_integer_contract_accepts_integer_and_rejects_decimal(): void
    {
        $form = $this->numberForm();
        $url = route('forms.show', $form->slug);

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'amount' => '1250000',
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame('1250000', FormSubmission::query()->sole()->payload['amount']);

        FormSubmission::query()->delete();
        $this->from($url)->post(route('forms.submit', $form->slug), [
            'amount' => '1250000.5',
        ])->assertRedirect($url)->assertSessionHasErrors('amount');
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_decimal_places_accept_valid_precision_and_reject_extra_digits(): void
    {
        $form = $this->numberForm(allowDecimals: true, decimalPlaces: 2);
        $url = route('forms.show', $form->slug);

        foreach (['12', '12.5', '12.50'] as $value) {
            $this->from($url)->post(route('forms.submit', $form->slug), [
                'amount' => $value,
            ])->assertRedirect($url)->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['12', '12.5', '12.50'],
            FormSubmission::query()->orderBy('id')->get()->pluck('payload.amount')->all(),
        );

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'amount' => '12.567',
        ])->assertRedirect($url)->assertSessionHasErrors('amount');
    }

    public function test_separator_and_persian_digits_are_presentation_only_and_snapshot_is_readable(): void
    {
        $form = $this->numberForm(thousandsSeparator: true, allowDecimals: true);
        $url = route('forms.show', $form->slug);

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'amount' => '۱٬۲۵۰٬۰۰۰٫۵۰',
        ])->assertRedirect($url)->assertSessionHasNoErrors();

        $submission = FormSubmission::query()->sole();
        $this->assertSame('1250000.50', $submission->payload['amount']);
        $this->assertSame(
            '1,250,000.50',
            data_get($submission->payload, '_answer_snapshot.0.display_value'),
        );
        $this->assertStringNotContainsString(',', $submission->payload['amount']);
    }

    public function test_required_error_restores_formatted_old_input_in_all_shared_renderers(): void
    {
        $form = $this->numberForm(thousandsSeparator: true, allowDecimals: true);
        $url = route('forms.show', $form->slug);

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'amount' => '1250000.567',
        ])->assertRedirect($url)->assertSessionHasErrors('amount');

        $html = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('value="1,250,000.567"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('data-form-number', $html);
        $this->assertStringContainsString('inputmode="decimal"', $html);

        $this->post(route('forms.modal', $form->slug))
            ->assertOk()
            ->assertSee('data-form-number', false);

        $this->from($url)->post(route('forms.submit', $form->slug), [])
            ->assertSessionHasErrors('amount');
    }

    public function test_number_javascript_formats_on_blur_and_submits_canonical_value(): void
    {
        $source = file_get_contents(resource_path('js/components/form-number-inputs.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("input.addEventListener('blur', format)", $source);
        $this->assertStringContainsString("input.form?.addEventListener('submit', canonicalize)", $source);
        $this->assertStringContainsString('initFormNumberInputs()', $app);
        $this->assertStringNotContainsString('parseFloat', $source);
        $this->assertStringNotContainsString('Number.parseFloat', $source);
    }

    private function numberForm(
        bool $thousandsSeparator = false,
        bool $allowDecimals = false,
        int $decimalPlaces = 2,
    ): Form {
        return Form::query()->create([
            'name' => 'فرم عددی',
            'slug' => 'number-form-'.Form::query()->count(),
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [[
                'key' => 'amount',
                'label' => 'مبلغ',
                'type' => 'number',
                'required' => true,
                'settings' => [
                    'thousands_separator' => $thousandsSeparator,
                    'allow_decimals' => $allowDecimals,
                    'decimal_places' => $decimalPlaces,
                ],
            ]]],
            'settings' => [],
        ]);
    }
}
