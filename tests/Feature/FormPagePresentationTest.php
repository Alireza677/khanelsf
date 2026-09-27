<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormPagePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_form_uses_schema_pages_shared_hero_and_page_only_cards(): void
    {
        $form = $this->form();
        $this->get(route('forms.show', $form->slug))->assertOk()
            ->assertSee('shared-hero')
            ->assertSee('نام داخلی فرم')
            ->assertSee('مرحله ۱ از ۲')
            ->assertSee('مرحله ۲ از ۲')
            ->assertSee('data-page-step-link="1"', false)
            ->assertSee('form-page__choice-title')
            ->assertSee('توضیح گزینه')
            ->assertSee('data-form-page', false)
            ->assertDontSee('data-page-result-link', false)
            ->assertDontSee('data-multi-step-form', false);

        $this->post(route('forms.modal', $form->slug))->assertOk()
            ->assertSee('data-multi-step-form', false)
            ->assertSee('form-radio-option')
            ->assertDontSee('data-form-page', false)
            ->assertDontSee('form-page__choice-title')
            ->assertDontSee('shared-hero');
    }

    public function test_flags_public_title_and_labels_do_not_change_schema(): void
    {
        $form = $this->form(['presentation' => [
            'show_hero' => false, 'show_stepper' => false, 'show_step_counter' => false,
            'show_step_description' => false, 'title' => 'عنوان عمومی',
            'previous_button_label' => 'بازگشت', 'next_button_label' => 'ادامه',
        ]]);
        $this->get(route('forms.show', $form->slug))->assertOk()
            ->assertSee('عنوان عمومی')->assertSee('بازگشت')->assertSee('ادامه')
            ->assertDontSee('form-page__counter')->assertDontSee('data-page-step-link', false)
            ->assertDontSee('class="content-block shared-hero', false)->assertDontSee('شرح مرحله اول');
    }

    public function test_final_validation_preserves_answers_and_marks_first_schema_step(): void
    {
        $form = $this->form();
        $this->from(route('forms.show', $form->slug))->post(route('forms.submit', $form->slug), [
            '_display_mode' => 'page', 'contact' => 'پاسخ مرحله آخر',
        ])->assertSessionHasErrors('choice');

        $html = $this->get(route('forms.show', $form->slug))->assertOk()
            ->assertSee('value="پاسخ مرحله آخر"', false)->getContent();
        $this->assertMatchesRegularExpression('/data-form-step="0"[\s\S]*?class="form-error"[\s\S]*?data-form-step="1"/', $html);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_editor_saves_presentation_inside_existing_settings_without_replacing_schema(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form(['success_message' => 'پیام موجود']);
        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('نمایش و صفحه فرم')
            ->assertSet('data.settings.presentation.show_hero', true)
            ->set('data.settings.presentation.title', 'عنوان جدید')
            ->set('data.settings.presentation.show_stepper', false)
            ->call('save')->assertHasNoFormErrors();

        $form->refresh();
        $this->assertSame('عنوان جدید', data_get($form->settings, 'presentation.title'));
        $this->assertFalse(data_get($form->settings, 'presentation.show_stepper'));
        $this->assertSame('پیام موجود', $form->settings['success_message']);
        $this->assertSame(['page', 'radio', 'page', 'text'], array_column($form->schema['fields'], 'type'));
        $this->assertSame('توضیح گزینه', data_get($form->schema, 'fields.1.options.0.description'));
        $this->assertSame('heroicon-o-home', data_get($form->schema, 'fields.1.options.0.icon'));
    }

    public function test_only_calculators_offer_real_result_in_a_dialog(): void
    {
        $form = $this->form();
        $form->update(['type' => 'calculator']);
        $state = ['form_id' => $form->id, 'calculation_result' => ['result' => 'پیشنهاد واقعی'], 'report_url' => '/report'];
        $this->withSession(['calculator_result_state' => $state])->get(route('forms.show', $form->slug))
            ->assertOk()->assertSee('data-page-result-link', false)
            ->assertSee('پیشنهاد واقعی')->assertSee('data-calculator-result-modal', false)
            ->assertDontSee('class="form-page__result"', false);
        $this->withSession(['calculator_result_state' => [...$state, 'display_mode' => 'modal']])
            ->get(route('forms.show', $form->slug))->assertOk()->assertSee('data-calculator-result-modal', false);
        $form->update(['type' => 'normal']);
        $this->withSession(['calculator_result_state' => $state])->get(route('forms.show', $form->slug))
            ->assertOk()->assertDontSee('data-calculator-result-modal', false);
    }

    private function form(array $settings = []): Form
    {
        return Form::query()->create([
            'name' => 'نام داخلی فرم', 'slug' => 'page-presentation', 'status' => 'published',
            'type' => 'normal', 'schema_version' => 2, 'settings' => $settings,
            'schema' => ['fields' => [
                ['key' => 'first', 'type' => 'page', 'label' => 'سؤال اول', 'description' => 'شرح مرحله اول'],
                ['key' => 'choice', 'type' => 'radio', 'label' => 'انتخاب کنید', 'required' => true,
                    'options' => [['value' => 'a', 'label' => 'گزینه اول', 'description' => 'توضیح گزینه', 'icon' => 'heroicon-o-home']]],
                ['key' => 'last', 'type' => 'page', 'label' => 'سؤال آخر'],
                ['key' => 'contact', 'type' => 'text', 'label' => 'اطلاعات تماس'],
            ]],
        ]);
    }
}
