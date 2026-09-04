<?php

namespace Tests\Feature;

use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\LeadResource;
use App\Models\Form;
use App\Models\Lead;
use App\Models\User;
use App\Services\Calculators\CalculatorManager;
use App\Services\CalculatorSubmissionReport;
use App\Services\FormSubmissionPresenter;
use App\Services\FormSubmissionService;
use App\Services\LeadSubmissionPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CalculatorEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private const SCORE_FIELD = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const SCORE_OPTION = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    private const RULE_FIELD = '01ARZ3NDEKTSV4RRFFQ69G5FAX';

    private const RULE_OPTION_ONE = '01ARZ3NDEKTSV4RRFFQ69G5FAY';

    private const RULE_OPTION_TWO = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const RULE_ONE = '01ARZ3NDEKTSV4RRFFQ69G5FB0';

    private const RULE_TWO = '01ARZ3NDEKTSV4RRFFQ69G5FB1';

    public static function operatorCases(): array
    {
        return [
            'radio equals' => ['radio', 'equals', 'one', 'one', null],
            'image choice not equals' => ['image_choice', 'not_equals', 'one', 'two', null],
            'legacy radio card equals' => ['radio_card', 'equals', 'two', 'two', null],
            'select not equals' => ['select', 'not_equals', 'one', 'two', null],
            'checkbox contains' => ['checkbox', 'contains', ['one', 'two'], 'two', null],
            'checkbox not contains' => ['checkbox', 'not_contains', ['one'], 'two', null],
            'number equals' => ['number', 'equals', '4', null, 4],
            'number not equals' => ['number', 'not_equals', '4', null, 5],
            'number greater than' => ['number', 'greater_than', '4', null, 3],
            'number greater than or equal' => ['number', 'greater_than_or_equal', '۴', null, 4],
            'number less than' => ['number', 'less_than', '4', null, 5],
            'number less than or equal' => ['number', 'less_than_or_equal', '4', null, 4],
        ];
    }

    #[DataProvider('operatorCases')]
    public function test_supported_operators_exclude_profiles_when_the_condition_matches(
        string $type,
        string $operator,
        mixed $answer,
        ?string $expectedOption,
        int|float|null $expectedNumber,
    ): void {
        $field = $this->eligibilityField($type);
        $rule = $this->rule(
            operator: $operator,
            optionId: $expectedOption === 'two' ? self::RULE_OPTION_TWO : self::RULE_OPTION_ONE,
            numberValue: $expectedNumber,
        );
        $form = $this->calculatorForm([$field], [$rule], slug: 'operator-'.str_replace('_', '-', $operator).'-'.$type);

        $result = app(CalculatorManager::class)->calculate($form, [
            'score_choice' => 'selected',
            'criterion' => $answer,
        ]);

        $this->assertFalse($result->eligibility['masonry']['eligible']);
        $this->assertSame(self::RULE_ONE, $result->eligibility['masonry']['reasons'][0]['rule_id']);
    }

    public function test_ineligible_highest_score_is_excluded_from_winner_and_competitive_ranking(): void
    {
        $form = $this->calculatorForm(
            [$this->eligibilityField('number')],
            [$this->rule('greater_than_or_equal', numberValue: 4)],
            slug: 'eligibility-winner',
        );

        $result = app(CalculatorManager::class)->calculate($form, [
            'score_choice' => 'selected',
            'criterion' => '4',
            'eligibility' => ['masonry' => ['eligible' => true]],
            'excluded_profiles' => [],
            'reason' => 'tampered',
            'scores' => ['masonry' => 9999],
        ]);

        $this->assertSame(['masonry' => 100, 'lsf' => 80, 'steel' => 70], $result->scores);
        $this->assertSame('lsf', $result->recommendedMethod);
        $this->assertSame(['lsf', 'steel', 'masonry'], array_column($result->ranking, 'key'));
        $this->assertSame([1, 2, null], array_column($result->ranking, 'rank'));
        $this->assertFalse($result->ranking[2]['eligible']);
    }

    public function test_multiple_rules_preserve_auditable_reasons_and_one_rule_can_exclude_multiple_profiles(): void
    {
        $field = $this->eligibilityField('radio');
        $rules = [
            $this->rule('equals', profiles: ['masonry', 'steel'], reason: 'محدودیت اول'),
            $this->rule('equals', profiles: ['masonry'], reason: 'محدودیت دوم', ruleId: self::RULE_TWO),
        ];
        $form = $this->calculatorForm([$field], $rules, slug: 'multiple-eligibility-rules');
        $result = app(CalculatorManager::class)->calculate($form, [
            'score_choice' => 'selected',
            'criterion' => 'one',
        ]);

        $this->assertFalse($result->eligibility['masonry']['eligible']);
        $this->assertFalse($result->eligibility['steel']['eligible']);
        $this->assertCount(2, $result->eligibility['masonry']['reasons']);
        $this->assertSame(['محدودیت اول', 'محدودیت دوم'], array_column($result->eligibility['masonry']['reasons'], 'message'));
    }

    public function test_eligibility_is_applied_before_the_existing_tie_policy(): void
    {
        $form = $this->calculatorForm(
            [$this->eligibilityField('radio')],
            [$this->rule('equals', profiles: ['first'])],
            slug: 'eligibility-tie',
            recommendations: ['first' => 'اول', 'second' => 'دوم', 'third' => 'سوم'],
            scoreMap: ['first' => 80, 'second' => 80, 'third' => 30],
        );
        $result = app(CalculatorManager::class)->calculate($form, [
            'score_choice' => 'selected',
            'criterion' => 'one',
        ]);

        $this->assertSame('second', $result->recommendedMethod);
        $this->assertSame(['second', 'third', 'first'], array_column($result->ranking, 'key'));
        $this->assertSame([1, 2, null], array_column($result->ranking, 'rank'));
    }

    public function test_all_ineligible_is_safe_and_renders_from_the_submission_snapshot_everywhere(): void
    {
        $form = $this->calculatorForm(
            [$this->eligibilityField('radio')],
            [$this->rule('equals', profiles: ['masonry', 'lsf', 'steel'])],
            slug: 'all-ineligible',
        );
        $form->update(['lead_generation_enabled' => true]);
        $submission = app(FormSubmissionService::class)->submit($form, [
            'score_choice' => 'selected',
            'criterion' => 'one',
        ]);
        $result = $submission->calculation_result;

        $this->assertNull($result['recommended_method']);
        $this->assertNull($result['result']);
        $this->assertTrue($result['no_eligible_recommendation']);
        $this->assertSame([null, null, null], array_column($result['ranking'], 'rank'));
        $this->assertSame($result, $submission->lead->calculation_result);

        $modal = view('forms._calculator-result-modal', [
            'form' => $form,
            'calculationResult' => $result,
            'modalId' => 'all-ineligible-modal',
        ])->render();
        $report = view('reports.calculator-submission', app(CalculatorSubmissionReport::class)->data($submission))->render();

        $this->assertStringContainsString('هیچ گزینه واجد شرایطی یافت نشد', $modal);
        $this->assertStringContainsString('گزینه‌های خارج‌شده', $modal);
        $this->assertStringContainsString('هیچ گزینه واجد شرایطی یافت نشد', $report);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('خارج از شرایط');
        $this->get(LeadResource::getUrl('view', ['record' => $submission->lead]))
            ->assertOk()
            ->assertSee('خارج‌شده');
    }

    public function test_optional_unanswered_fields_never_match_including_negative_operators(): void
    {
        $fields = [
            $this->eligibilityField('radio', self::RULE_FIELD, 'optional_radio'),
            $this->eligibilityField('checkbox', '01ARZ3NDEKTSV4RRFFQ69G5FB2', 'optional_checkbox', '01ARZ3NDEKTSV4RRFFQ69G5FB3', '01ARZ3NDEKTSV4RRFFQ69G5FB4'),
            $this->eligibilityField('number', '01ARZ3NDEKTSV4RRFFQ69G5FB5', 'optional_number'),
        ];
        $rules = [
            $this->rule('not_equals', fieldId: self::RULE_FIELD),
            $this->rule('not_contains', fieldId: '01ARZ3NDEKTSV4RRFFQ69G5FB2', optionId: '01ARZ3NDEKTSV4RRFFQ69G5FB3', ruleId: self::RULE_TWO),
            $this->rule('greater_than', fieldId: '01ARZ3NDEKTSV4RRFFQ69G5FB5', numberValue: 0, ruleId: '01ARZ3NDEKTSV4RRFFQ69G5FB6'),
        ];
        $form = $this->calculatorForm($fields, $rules, slug: 'optional-eligibility-fields');
        $result = app(CalculatorManager::class)->calculate($form, ['score_choice' => 'selected']);

        $this->assertTrue($result->eligibility['masonry']['eligible']);
        $this->assertSame([], $result->eligibility['masonry']['reasons']);
    }

    public function test_http_tampering_cannot_override_server_side_eligibility(): void
    {
        $form = $this->calculatorForm(
            [$this->eligibilityField('number')],
            [$this->rule('greater_than_or_equal', numberValue: 4)],
            slug: 'eligibility-tampering',
        );

        $this->post(route('forms.submit', $form->slug), [
            'score_choice' => 'selected',
            'criterion' => '4',
            'eligibility' => ['masonry' => ['eligible' => true]],
            'excluded_profiles' => [],
            'reason' => 'client reason',
            'scores' => ['masonry' => 9999],
        ])->assertSessionHasNoErrors();

        $submission = $form->submissions()->sole();
        $this->assertFalse($submission->calculation_result['eligibility']['masonry']['eligible']);
        $this->assertSame('lsf', $submission->calculation_result['recommended_method']);
        $this->assertArrayNotHasKey('eligibility', $submission->payload);
        $this->assertArrayNotHasKey('scores', $submission->payload);
    }

    public function test_eligibility_outcome_winner_ranking_and_reasons_are_historical_snapshots(): void
    {
        $form = $this->calculatorForm(
            [$this->eligibilityField('radio')],
            [$this->rule('equals', reason: 'دلیل تاریخی')],
            slug: 'eligibility-history',
        );
        $form->update(['lead_generation_enabled' => true]);
        $submission = app(FormSubmissionService::class)->submit($form, [
            'score_choice' => 'selected',
            'criterion' => 'one',
        ]);
        $before = $submission->calculation_result;

        $schema = $form->schema;
        data_set($schema, 'calculator.eligibility_rules', []);
        data_set($schema, 'calculator.recommendations', ['steel' => 'عنوان جدید', 'masonry' => 'بنایی جدید', 'lsf' => 'LSF جدید']);
        data_set($schema, 'fields.0.options.0.scores', ['masonry' => -100, 'lsf' => 999]);
        $form->update(['schema' => $schema]);
        $submission = $submission->fresh();

        $this->assertSame($before, $submission->calculation_result);
        $this->assertSame($before, $submission->lead->calculation_result);
        $this->assertSame('دلیل تاریخی', $before['eligibility']['masonry']['reasons'][0]['message']);
        $this->assertSame(
            array_column(app(FormSubmissionPresenter::class)->calculationScores($submission), 'label'),
            array_column(app(CalculatorSubmissionReport::class)->data($submission)['scores'], 'label'),
        );
        $this->assertSame(
            array_column(app(FormSubmissionPresenter::class)->calculationScores($submission), 'label'),
            array_column(app(LeadSubmissionPresenter::class)->scores($submission->lead), 'label'),
        );
    }

    public function test_legacy_calculation_without_eligibility_remains_renderable_and_unranked_state_is_not_recomputed(): void
    {
        $form = $this->calculatorForm([], [], slug: 'legacy-without-eligibility');
        $submission = $form->submissions()->create([
            'source' => 'website',
            'payload' => ['score_choice' => 'selected'],
            'calculation_result' => [
                'recommended_method' => 'masonry',
                'result' => 'بنایی قدیمی',
                'scores' => ['masonry' => 100, 'lsf' => 80],
                'score_labels' => ['masonry' => 'بنایی قدیمی', 'lsf' => 'LSF قدیمی'],
            ],
            'submitted_at' => now(),
        ]);
        $lead = Lead::query()->create([
            'form_submission_id' => $submission->getKey(),
            'form_id' => $form->getKey(),
            'calculation_result' => $submission->calculation_result,
            'status' => 'new',
            'source' => 'website',
        ]);

        $rows = app(FormSubmissionPresenter::class)->calculationScores($submission);
        $this->assertSame([null, null], array_column($rows, 'eligible'));
        $this->assertSame(['ارزیابی‌نشده', 'ارزیابی‌نشده'], array_column($rows, 'eligibility_label'));
        $this->assertNotEmpty(app(CalculatorSubmissionReport::class)->data($submission)['scores']);
        $this->assertStringContainsString('بنایی قدیمی', view('forms._calculator-result-modal', [
            'form' => $form,
            'calculationResult' => $submission->calculation_result,
            'modalId' => 'legacy-eligibility-modal',
        ])->render());

        $this->actingAs(User::factory()->admin()->create());
        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('بنایی قدیمی');
        $this->get(LeadResource::getUrl('view', ['record' => $lead]))
            ->assertOk()
            ->assertSee('بنایی قدیمی');
    }

    private function calculatorForm(
        array $eligibilityFields,
        array $rules,
        string $slug,
        array $recommendations = ['masonry' => 'بنایی', 'lsf' => 'LSF', 'steel' => 'فولادی'],
        array $scoreMap = ['masonry' => 100, 'lsf' => 80, 'steel' => 70],
    ): Form {
        return Form::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => 'page',
            'lead_generation_enabled' => false,
            'type' => 'calculator',
            'calculator_identifier' => $slug.'_v1',
            'schema_version' => 2,
            'schema' => [
                'fields' => [[
                    'field_id' => self::SCORE_FIELD,
                    'key' => 'score_choice',
                    'label' => 'انتخاب امتیاز',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [[
                        'option_id' => self::SCORE_OPTION,
                        'value' => 'selected',
                        'label' => 'انتخاب',
                        'scores' => $scoreMap,
                    ]],
                ], ...$eligibilityFields],
                'calculator' => [
                    'recommendations' => $recommendations,
                    'eligibility_rules' => $rules,
                ],
            ],
            'settings' => [],
        ]);
    }

    private function eligibilityField(
        string $type,
        string $fieldId = self::RULE_FIELD,
        string $key = 'criterion',
        string $optionOne = self::RULE_OPTION_ONE,
        string $optionTwo = self::RULE_OPTION_TWO,
    ): array {
        $field = [
            'field_id' => $fieldId,
            'key' => $key,
            'label' => 'معیار صلاحیت',
            'type' => $type,
            'required' => false,
        ];

        if ($type === 'number') {
            $field['settings'] = ['allow_decimals' => true, 'decimal_places' => 2];

            return $field;
        }

        $field['options'] = [
            ['option_id' => $optionOne, 'value' => 'one', 'label' => 'یک', 'scores' => []],
            ['option_id' => $optionTwo, 'value' => 'two', 'label' => 'دو', 'scores' => []],
        ];

        return $field;
    }

    private function rule(
        string $operator,
        ?string $optionId = self::RULE_OPTION_ONE,
        int|float|null $numberValue = null,
        array $profiles = ['masonry'],
        string $reason = 'خارج از محدوده مجاز',
        string $ruleId = self::RULE_ONE,
        string $fieldId = self::RULE_FIELD,
    ): array {
        return [
            'rule_id' => $ruleId,
            'field_id' => $fieldId,
            'operator' => $operator,
            'option_id' => $optionId,
            'number_value' => $numberValue,
            'profiles' => $profiles,
            'effect' => 'exclude',
            'reason' => $reason,
        ];
    }
}
