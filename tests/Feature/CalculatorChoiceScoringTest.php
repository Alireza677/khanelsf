<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Services\Calculators\CalculatorManager;
use App\Services\CalculatorSubmissionReport;
use App\Services\FormSubmissionPresenter;
use App\Services\FormSubmissionService;
use App\Services\LeadSubmissionPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CalculatorChoiceScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_radio_scores_multiple_profiles_without_trusting_request_scores(): void
    {
        $form = $this->calculatorForm([[
            'key' => 'priority',
            'label' => 'اولویت',
            'type' => 'radio',
            'required' => true,
            'options' => [[
                'value' => 'high',
                'label' => 'زیاد',
                'scores' => ['lsf' => 10, 'steel' => 8, 'concrete' => 6, 'unknown' => 999],
            ]],
        ]], slug: 'radio-calculator');

        $result = app(CalculatorManager::class)->calculate($form, [
            'priority' => 'high',
            'scores' => ['masonry' => 9999],
        ]);

        $this->assertSame([
            'lsf' => 10,
            'steel' => 8,
            'concrete' => 6,
            'masonry' => 0,
        ], $result->scores);
        $this->assertSame('lsf', $result->recommendedMethod);
    }

    public function test_standard_radio_preserves_decimal_negative_and_zero_scores(): void
    {
        $form = $this->calculatorForm([[
            'key' => 'criterion',
            'label' => 'معیار',
            'type' => 'radio',
            'required' => true,
            'options' => [[
                'value' => 'mixed',
                'label' => 'ترکیبی',
                'scores' => ['lsf' => 1.5, 'steel' => -2, 'concrete' => 0],
            ]],
        ]], slug: 'radio-numeric-calculator');

        $result = app(CalculatorManager::class)->calculate($form, ['criterion' => 'mixed']);

        $this->assertSame(1.5, $result->scores['lsf']);
        $this->assertSame(-2, $result->scores['steel']);
        $this->assertSame(0, $result->scores['concrete']);
        $this->assertSame(0, $result->scores['masonry']);
    }

    public function test_radio_validation_rejects_an_unknown_option_before_calculation(): void
    {
        $form = $this->calculatorForm([$this->choiceField('priority', 'radio', true)], slug: 'radio-validation-calculator');

        $this->from(route('forms.show', $form->slug))
            ->post(route('forms.submit', $form->slug), ['priority' => 'tampered'])
            ->assertSessionHasErrors('priority');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_checkbox_scores_one_and_multiple_selected_options_additively(): void
    {
        $form = $this->calculatorForm([[
            'key' => 'priorities',
            'label' => 'اولویت‌ها',
            'type' => 'checkbox',
            'required' => true,
            'options' => [
                ['value' => 'earthquake', 'label' => 'زلزله', 'scores' => ['lsf' => 10, 'steel' => 9, 'concrete' => 7, 'masonry' => 2]],
                ['value' => 'speed', 'label' => 'سرعت', 'scores' => ['lsf' => 10, 'steel' => 8, 'concrete' => 4, 'masonry' => 3]],
                ['value' => 'thermal', 'label' => 'عایق', 'scores' => ['lsf' => 9, 'steel' => 5, 'concrete' => 7, 'masonry' => 6]],
            ],
        ]], slug: 'checkbox-calculator');

        $single = app(CalculatorManager::class)->calculate($form, ['priorities' => ['earthquake']]);
        $multiple = app(CalculatorManager::class)->calculate($form, [
            'priorities' => ['earthquake', 'speed', 'thermal'],
        ]);

        $this->assertSame(['lsf' => 10, 'steel' => 9, 'concrete' => 7, 'masonry' => 2], $single->scores);
        $this->assertSame(['lsf' => 29, 'steel' => 22, 'concrete' => 18, 'masonry' => 11], $multiple->scores);
        $this->assertSame(['earthquake', 'speed', 'thermal'], $multiple->answers['priorities']);
        $this->assertSame('زلزله، سرعت، عایق', $multiple->answerLabels['priorities']);
    }

    public function test_checkbox_validation_rejects_unknown_and_duplicate_values(): void
    {
        $form = $this->calculatorForm([$this->choiceField('priorities', 'checkbox', true)], slug: 'checkbox-validation-calculator');
        $url = route('forms.show', $form->slug);

        $this->from($url)
            ->post(route('forms.submit', $form->slug), ['priorities' => ['one', 'tampered']])
            ->assertSessionHasErrors('priorities.1');

        $this->from($url)
            ->post(route('forms.submit', $form->slug), ['priorities' => ['one', 'one']])
            ->assertSessionHasErrors('priorities.0');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_all_optional_scoreable_choice_types_skip_empty_answers(): void
    {
        $fields = [
            $this->choiceField('image', 'image_choice', false),
            $this->choiceField('legacy_card', 'radio_card', false),
            $this->choiceField('radio', 'radio', false),
            $this->choiceField('checks', 'checkbox', false),
        ];
        $form = $this->calculatorForm($fields, slug: 'optional-choice-calculator');

        $result = app(CalculatorManager::class)->calculate($form, ['checks' => []]);

        $this->assertSame([], $result->answers);
        $this->assertSame(['lsf' => 0, 'steel' => 0, 'concrete' => 0, 'masonry' => 0], $result->scores);
        $this->assertSame('lsf', $result->recommendedMethod);

        $this->post(route('forms.submit', $form->slug), ['checks' => []])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_every_required_scoreable_choice_type_is_rejected_when_empty(): void
    {
        foreach (['image_choice', 'radio_card', 'radio', 'checkbox'] as $index => $type) {
            $key = 'required_choice';
            $form = $this->calculatorForm(
                [$this->choiceField($key, $type, true)],
                slug: "required-choice-{$index}",
            );

            $this->from(route('forms.show', $form->slug))
                ->post(route('forms.submit', $form->slug), [])
                ->assertSessionHasErrors($key);
        }

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_malformed_choice_answers_fail_predictably_in_the_calculator(): void
    {
        $form = $this->calculatorForm([
            $this->choiceField('radio', 'radio', false),
            $this->choiceField('checks', 'checkbox', false),
        ], slug: 'malformed-choice-calculator');
        $payloads = [
            ['radio' => ['one']],
            ['checks' => 'one'],
            ['checks' => ['one', 'one']],
        ];

        foreach ($payloads as $payload) {
            try {
                app(CalculatorManager::class)->calculate($form, $payload);
                $this->fail('Malformed calculator answer was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('Invalid answer', $exception->getMessage());
            }
        }
    }

    public function test_image_choice_and_legacy_radio_card_keep_their_additive_behavior(): void
    {
        $form = $this->calculatorForm([
            $this->choiceField('image', 'image_choice', true),
            $this->choiceField('legacy_card', 'radio_card', true),
        ], slug: 'legacy-choice-calculator');

        $result = app(CalculatorManager::class)->calculate($form, [
            'image' => 'one',
            'legacy_card' => 'one',
        ]);

        $this->assertSame(['lsf' => 6, 'steel' => 4, 'concrete' => 0, 'masonry' => 0], $result->scores);
    }

    public function test_ranking_is_descending_and_ties_keep_recommendation_order(): void
    {
        $ranked = $this->calculatorForm([[
            'key' => 'answer', 'label' => 'پاسخ', 'type' => 'radio', 'required' => true,
            'options' => [[
                'value' => 'selected', 'label' => 'انتخاب',
                'scores' => ['lsf' => 30, 'steel' => 50, 'concrete' => 40, 'masonry' => 10],
            ]],
        ]], slug: 'ranking-calculator');
        $tied = $this->calculatorForm([[
            'key' => 'answer', 'label' => 'پاسخ', 'type' => 'radio', 'required' => true,
            'options' => [[
                'value' => 'selected', 'label' => 'انتخاب',
                'scores' => ['lsf' => 50, 'steel' => 50, 'concrete' => 30, 'masonry' => 10],
            ]],
        ]], slug: 'tie-calculator');

        $ranking = app(CalculatorManager::class)->calculate($ranked, ['answer' => 'selected']);
        $tie = app(CalculatorManager::class)->calculate($tied, ['answer' => 'selected']);

        $this->assertSame(['steel', 'concrete', 'lsf', 'masonry'], array_column($ranking->ranking, 'key'));
        $this->assertSame([1, 2, 3, 4], array_column($ranking->ranking, 'rank'));
        $this->assertSame('lsf', $tie->recommendedMethod);
        $this->assertSame(['lsf', 'steel', 'concrete', 'masonry'], array_column($tie->ranking, 'key'));
    }

    public function test_new_ranking_is_snapshot_driven_after_form_scores_labels_and_order_change(): void
    {
        $form = $this->calculatorForm([[
            'key' => 'answer', 'label' => 'پاسخ', 'type' => 'radio', 'required' => true,
            'options' => [[
                'value' => 'selected', 'label' => 'انتخاب',
                'scores' => ['lsf' => 30, 'steel' => 50, 'concrete' => 40, 'masonry' => 10],
            ]],
        ]], slug: 'ranking-snapshot-calculator');
        $form->update(['lead_generation_enabled' => true]);
        $submission = app(FormSubmissionService::class)->submit($form, ['answer' => 'selected']);
        $before = $submission->calculation_result;
        $this->assertSame($before, $submission->lead->calculation_result);

        $form->update(['schema' => [
            'fields' => [[
                'key' => 'answer', 'label' => 'عنوان جدید', 'type' => 'radio', 'required' => true,
                'options' => [[
                    'value' => 'selected', 'label' => 'گزینه جدید',
                    'scores' => ['lsf' => 999, 'steel' => -999],
                ]],
            ]],
            'calculator' => ['recommendations' => [
                'masonry' => 'بنایی جدید', 'concrete' => 'بتن جدید', 'steel' => 'فولاد جدید', 'lsf' => 'LSF جدید',
            ]],
        ]]);

        $submission = $submission->fresh();
        $this->assertSame($before, $submission->calculation_result);
        $this->assertSame(
            ['سازه فولادی', 'بتن آرمه', 'LSF', 'بنایی کلاف‌دار'],
            array_column($submission->calculation_result['ranking'], 'label'),
        );
        $this->assertSame(
            ['سازه فولادی', 'بتن آرمه', 'LSF', 'بنایی کلاف‌دار'],
            array_column(app(CalculatorSubmissionReport::class)->data($submission)['scores'], 'label'),
        );
        $this->assertSame(
            ['سازه فولادی', 'بتن آرمه', 'LSF', 'بنایی کلاف‌دار'],
            array_column(app(FormSubmissionPresenter::class)->calculationScores($submission), 'label'),
        );
        $this->assertSame(
            ['سازه فولادی', 'بتن آرمه', 'LSF', 'بنایی کلاف‌دار'],
            array_column(app(LeadSubmissionPresenter::class)->scores($submission->lead), 'label'),
        );
    }

    public function test_legacy_result_without_ranking_uses_stored_score_order_and_labels(): void
    {
        $form = $this->calculatorForm([$this->choiceField('answer', 'image_choice', true)], slug: 'legacy-ranking-fallback');
        $submission = $form->submissions()->create([
            'source' => 'website',
            'payload' => ['answer' => 'one'],
            'calculation_result' => [
                'recommended_method' => 'lsf',
                'result' => 'LSF قدیمی',
                'scores' => ['lsf' => 12, 'steel' => 7],
                'score_labels' => ['lsf' => 'LSF قدیمی', 'steel' => 'فولاد قدیمی'],
            ],
            'submitted_at' => now(),
        ]);

        $rows = app(FormSubmissionPresenter::class)->calculationScores($submission);
        $this->assertSame(['LSF قدیمی', 'فولاد قدیمی'], array_column($rows, 'label'));
        $this->assertSame([null, null], array_column($rows, 'rank'));

        $html = view('forms._calculator-result-modal', [
            'form' => $form,
            'calculationResult' => $submission->calculation_result,
            'modalId' => 'legacy-ranking-modal',
        ])->render();
        $this->assertStringContainsString('LSF قدیمی', $html);
        $this->assertStringContainsString('فولاد قدیمی', $html);
        $this->assertStringNotContainsString('رتبه 1', $html);
    }

    private function choiceField(string $key, string $type, bool $required): array
    {
        return [
            'key' => $key,
            'label' => $key,
            'type' => $type,
            'required' => $required,
            'options' => [
                ['value' => 'one', 'label' => 'گزینه یک', 'scores' => ['lsf' => 3, 'steel' => 2]],
                ['value' => 'two', 'label' => 'گزینه دو', 'scores' => ['lsf' => 1, 'steel' => 4]],
            ],
        ];
    }

    private function calculatorForm(array $fields, string $slug): Form
    {
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
                'fields' => $fields,
                'calculator' => ['recommendations' => [
                    'lsf' => 'LSF',
                    'steel' => 'سازه فولادی',
                    'concrete' => 'بتن آرمه',
                    'masonry' => 'بنایی کلاف‌دار',
                ]],
            ],
            'settings' => [],
        ]);
    }
}
