<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\Page;
use App\Models\User;
use App\Services\Calculators\CalculatorManager;
use App\Services\FormSchema;
use App\Services\SubmissionAnswerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConstructionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_scoring_returns_the_expected_construction_recommendation(): void
    {
        $form = $this->calculatorForm();
        $result = app(CalculatorManager::class)->calculate($form, $this->answers());

        $this->assertSame('prefabricated', $result->recommendedMethod);
        $this->assertSame('پیش ساخته', $result->result);
        $this->assertSame([
            'prefabricated' => 22,
            'site_assembly' => 14,
            'site_build' => 8,
        ], $result->scores);
    }

    public function test_calculator_form_renders_definition_questions_and_contact_fields(): void
    {
        $form = $this->calculatorForm();

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertSee('حجم پروژه شما چیست؟')
            ->assertSee('name="project_volume"', false)
            ->assertSee('value="single_unit"', false)
            ->assertSee('data-multi-step-form', false)
            ->assertSee('data-step-next', false)
            ->assertSee('data-step-back', false)
            ->assertSee('name="phone"', false);
    }

    public function test_page_markers_render_as_real_initially_hidden_steps(): void
    {
        $form = $this->calculatorForm();
        $html = $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->getContent();

        preg_match_all('/<section\b[^>]*data-form-step="(\d+)"[^>]*>/', $html, $matches);

        $this->assertGreaterThan(1, count($matches[0]));
        $this->assertStringNotContainsString(' hidden', $matches[0][0]);
        $this->assertStringContainsString('aria-hidden="false"', $matches[0][0]);

        foreach (array_slice($matches[0], 1) as $hiddenStep) {
            $this->assertStringContainsString(' hidden', $hiddenStep);
            $this->assertStringContainsString('aria-hidden="true"', $hiddenStep);
        }

        $this->assertStringNotContainsString('name="project_profile"', $html);
        $this->assertStringNotContainsString('name="usage_and_design"', $html);
        $this->assertStringNotContainsString('name="delivery_conditions"', $html);
    }

    public function test_calculator_submission_stores_result_and_creates_attributed_lead(): void
    {
        $form = $this->calculatorForm();
        $page = Page::factory()->published()->create();

        $this->post(route('forms.context', $form->slug), [
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/calculator-source',
            '_context_block_id' => self::BLOCK_ID,
        ])->assertRedirect(route('forms.show', $form->slug));

        $this->post(route('forms.submit', $form->slug), [
            ...$this->answers(),
            'name' => 'Calculator Client',
            'phone' => '09120000000',
        ])
            ->assertRedirect()
            ->assertSessionHas('calculator_result_state.form_id', $form->getKey())
            ->assertSessionHas('calculator_result_state.calculation_result.result', 'پیش ساخته')
            ->assertSessionMissing('form_success');

        $submission = FormSubmission::query()->sole();
        $lead = Lead::query()->sole();

        $this->assertSame($this->answers(), array_intersect_key($submission->payload, $this->answers()));
        $this->assertSame('construction_process_v1', $submission->calculation_result['calculator_identifier']);
        $this->assertSame('پیش ساخته', $submission->calculation_result['result']);
        $this->assertSame(22, $submission->calculation_result['scores']['prefabricated']);
        $this->assertSame('تک واحد', $submission->calculation_result['answer_labels']['project_volume']);
        $projectVolumeSnapshot = collect($submission->payload[SubmissionAnswerSnapshot::PAYLOAD_KEY])
            ->firstWhere('field_key', 'project_volume');
        $this->assertSame('حجم پروژه شما چیست؟', $projectVolumeSnapshot['field_label']);
        $this->assertSame('single_unit', $projectVolumeSnapshot['raw_value']);
        $this->assertSame('تک واحد', $projectVolumeSnapshot['display_value']);
        $this->assertSame(
            'حجم پروژه شما چیست؟',
            $submission->calculation_result['answer_field_labels']['project_volume'],
        );
        $this->assertSame('پیش ساخته', $submission->calculation_result['score_labels']['prefabricated']);
        $this->assertSame('مونتاژ در سایت', $submission->calculation_result['score_labels']['site_assembly']);
        $this->assertSame($submission->calculation_result, $lead->calculation_result);
        $this->assertSame('Calculator Client', $lead->name);
        $this->assertSame($page->getKey(), $lead->page_id);
        $this->assertSame('/calculator-source', $lead->page_url);
        $this->assertSame(self::BLOCK_ID, $lead->block_id);

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertSee('data-calculator-result-modal', false)
            ->assertSee('data-calculator-result-close', false)
            ->assertSee('data-initial-step="last"', false)
            ->assertSee('نتیجه ارزیابی شما')
            ->assertSee('calculator-result-card__hero', false)
            ->assertSee('اطلاعات وارد شده توسط کاربر')
            ->assertSee('حجم پروژه شما چیست؟')
            ->assertSee('تک واحد')
            ->assertSee('مقایسه نتایج')
            ->assertSee('پیش ساخته')
            ->assertSee('مونتاژ در سایت')
            ->assertSee('ساخت و مونتاژ در سایت')
            ->assertSee('22')
            ->assertSee('برای دریافت مشاوره تخصصی با ما تماس بگیرید.')
            ->assertDontSee('prefabricated')
            ->assertDontSee('خلاصه پروژه')
            ->assertDontSee('مزایا یا توضیحات نتیجه')
            ->assertDontSee('اطلاعات شما با موفقیت دریافت شد.');
    }

    public function test_normal_form_submission_remains_without_calculation_data(): void
    {
        $form = Form::query()->create([
            'name' => 'Normal Form',
            'slug' => 'normal-form',
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 1,
            'schema' => ['fields' => [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ]],
        ]);

        $this->post(route('forms.submit', $form->slug), ['email' => 'normal@example.com'])
            ->assertRedirect()
            ->assertSessionHas('form_success')
            ->assertSessionMissing('calculator_result_state');

        $this->assertNull(FormSubmission::query()->sole()->calculation_result);
        $this->assertNull(Lead::query()->sole()->calculation_result);
    }

    public function test_calculator_submitted_from_cta_modal_redirects_with_result_state(): void
    {
        $form = $this->calculatorForm();

        $this->post(route('forms.submit', $form->slug), [
            ...$this->answers(),
            'name' => 'Modal Calculator Client',
            'phone' => '09121111111',
            '_display_mode' => 'modal',
        ])
            ->assertRedirect(route('forms.show', $form->slug))
            ->assertSessionHas('calculator_result_state.calculation_result.result', 'پیش ساخته')
            ->assertSessionMissing('form_success');

        $this->assertSame('پیش ساخته', FormSubmission::query()->sole()->calculation_result['result']);
        $this->assertSame('پیش ساخته', Lead::query()->sole()->calculation_result['result']);
    }

    public function test_result_card_renders_optional_snapshot_sections_when_available(): void
    {
        $form = $this->calculatorForm();
        $result = app(CalculatorManager::class)->calculate($form, $this->answers())->toArray();
        $result['reason'] = 'بالاترین امتیاز با نیازهای زمانی پروژه هماهنگ است.';
        $result['project_summary'] = 'یک پروژه با اولویت اجرای سریع.';
        $result['result_description'] = 'این روش سرعت اجرا و کنترل کیفیت مناسبی فراهم می‌کند.';
        $result['benefits'] = ['زمان اجرای کوتاه‌تر', 'کنترل کیفیت بهتر'];
        $result['outputs'] = [['label' => 'اولویت پروژه', 'value' => 'سرعت اجرا']];

        $html = view('forms._calculator-result-modal', [
            'form' => $form,
            'calculationResult' => $result,
            'modalId' => 'calculator-result-test',
        ])->render();

        $this->assertStringContainsString('بالاترین امتیاز با نیازهای زمانی پروژه هماهنگ است.', $html);
        $this->assertStringContainsString('خلاصه پروژه', $html);
        $this->assertStringContainsString('اولویت پروژه', $html);
        $this->assertStringContainsString('سرعت اجرا', $html);
        $this->assertStringContainsString('مزایا یا توضیحات نتیجه', $html);
        $this->assertStringContainsString('زمان اجرای کوتاه‌تر', $html);
        $this->assertStringContainsString('کنترل کیفیت بهتر', $html);
    }

    public function test_admin_can_create_a_calculator_form(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateForm::class)
            ->fillForm([
                'name' => 'Construction Recommendation',
                'slug' => 'construction-recommendation',
                'status' => 'published',
                'display_mode' => 'page',
                'type' => 'calculator',
                'calculator_identifier' => 'custom_quote_v1',
                'schema_version' => 2,
                'schema' => ['fields' => [
                    ['name' => 'project', 'label' => 'پروژه', 'type' => 'page'],
                    [
                        'name' => 'building_type',
                        'label' => 'نوع ساختمان',
                        'type' => 'radio_card',
                        'required' => true,
                        'options' => [[
                            'value' => 'villa',
                            'label' => 'ویلا',
                            'scores' => [
                                ['key' => 'prefab', 'score' => 3],
                                ['key' => 'onsite', 'score' => 0],
                            ],
                        ]],
                    ],
                ], 'calculator' => ['recommendations' => [
                    ['key' => 'prefab', 'label' => 'پیش ساخته'],
                    ['key' => 'onsite', 'label' => 'ساخت در محل'],
                ]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('forms', [
            'slug' => 'construction-recommendation',
            'type' => 'calculator',
            'calculator_identifier' => 'custom_quote_v1',
        ]);

        $saved = Form::query()->where('slug', 'construction-recommendation')->sole();
        $this->assertSame('radio_card', $saved->schema['fields'][1]['type']);
        $this->assertSame(3, app(FormSchema::class)->fields($saved)[1]['options'][0]['scores']['prefab']);
    }

    private function calculatorForm(): Form
    {
        $form = Form::query()->create([
            'name' => 'Construction Recommendation',
            'slug' => 'construction-recommendation',
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'calculator',
            'calculator_identifier' => 'construction_process_v1',
            'schema_version' => 1,
            'schema' => ['fields' => [
                ['name' => 'name', 'label' => 'نام', 'type' => 'text', 'required' => true],
                ['name' => 'phone', 'label' => 'تلفن', 'type' => 'tel', 'required' => true],
            ]],
        ]);

        $migration = require database_path('migrations/2026_07_16_000003_migrate_construction_calculator_to_schema.php');
        $migration->up();

        return $form->fresh();
    }

    private function answers(): array
    {
        return [
            'project_volume' => 'single_unit',
            'construction_space' => 'garden_land',
            'building_use' => 'movable',
            'architecture_style' => 'modern',
            'construction_duration' => 'one_to_three_months',
            'financing' => 'one_hundred_percent',
            'construction_environment' => 'urban',
            'transport_limits' => 'none',
        ];
    }
}
