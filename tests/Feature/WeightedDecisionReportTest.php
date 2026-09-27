<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Services\Calculators\CalculatorManager;
use App\Services\Calculators\DecisionReportBuilder;
use App\Services\CalculatorSubmissionReport;
use App\Services\FormSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightedDecisionReportTest extends TestCase
{
    use RefreshDatabase;

    private const IDS = [
        '01ARZ3NDEKTSV4RRFFQ69G5FAV', '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        '01ARZ3NDEKTSV4RRFFQ69G5FAX', '01ARZ3NDEKTSV4RRFFQ69G5FAY',
    ];

    public function test_report_uses_exact_resolved_factors_and_does_not_change_calculation(): void
    {
        $form = $this->calculator();
        $manager = app(CalculatorManager::class);
        $enabled = $manager->calculate($form, ['choice' => 'yes'])->toArray();
        $report = $enabled['decision_report'];
        $this->assertSame(1, $report['version']);
        $this->assertSame('lsf', $report['result_key']);
        $this->assertSame('سازه سبک', $report['result_label']);
        $this->assertSame('70.00', $report['suitability_percentage']);
        // Config requests one, but the existing top_factors selection remains the sole source.
        $this->assertCount(3, $report['factors']);
        $this->assertSame($enabled['top_factors'], array_map(function (array $factor): array {
            unset($factor['explanation']);

            return $factor;
        }, $report['factors']));
        $this->assertSame('توضیح ثبت‌شده سرعت', $report['factors'][0]['explanation']);
        $this->assertSame(DecisionReportBuilder::FALLBACK_EXPLANATION, $report['factors'][1]['explanation']);
        $this->assertSame('بر اساس پاسخ‌های شما، عوامل زیر بیشترین تأثیر را در پیشنهاد «سازه سبک» داشته‌اند.', $report['intro']);
        $this->assertStringContainsString('۷۰٫۰۰٪', $report['summary']);
        $this->assertStringContainsString("سرعت\nتوضیح ثبت‌شده سرعت", $report['rendered_text']);
        $this->assertSame($enabled, $manager->calculate($form, ['choice' => 'yes'])->toArray());

        $schema = $form->schema;
        $schema['calculator']['decision_report']['enabled'] = false;
        $form->schema = $schema;
        $disabled = $manager->calculate($form, ['choice' => 'yes'])->toArray();
        unset($enabled['decision_report']);
        $this->assertSame($enabled, $disabled);
        unset($schema['calculator']['decision_report']);
        $form->schema = $schema;
        $this->assertSame($disabled, $manager->calculate($form, ['choice' => 'yes'])->toArray());
    }

    public function test_no_score_no_eligible_and_simple_do_not_produce_reports(): void
    {
        $form = $this->calculator();
        $manager = app(CalculatorManager::class);
        $schema = $form->schema;
        foreach ($schema['calculator']['criteria'] as &$criterion) {
            $criterion['base_weight'] = 0;
        }
        unset($criterion);
        $form->schema = $schema;
        $result = $manager->calculate($form, [])->toArray();
        $this->assertTrue($result['no_score']);
        $this->assertArrayNotHasKey('decision_report', $result);

        $form = $this->calculator();
        $schema = $form->schema;
        $schema['calculator']['eligibility_rules'] = [[
            'rule_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB1',
            'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0',
            'operator' => 'equals', 'number_value' => 1,
            'profiles' => ['lsf', 'other'], 'effect' => 'exclude', 'reason' => 'نیازمند بررسی',
        ]];
        $form->schema = $schema;
        $result = $manager->calculate($form, ['limit' => 1])->toArray();
        $this->assertFalse($result['no_score']);
        $this->assertTrue($result['no_eligible_recommendation']);
        $this->assertArrayNotHasKey('decision_report', $result);

        $schema['calculator']['scoring_mode'] = 'simple';
        $form->schema = $schema;
        $simple = $manager->calculate($form, ['choice' => 'yes'])->toArray();
        $this->assertArrayNotHasKey('decision_report', $simple);
        unset($schema['calculator']['decision_report']);
        $form->schema = $schema;
        $this->assertSame($simple, $manager->calculate($form, ['choice' => 'yes'])->toArray());
    }

    public function test_submission_and_lead_preserve_report_and_both_views_read_historical_snapshot(): void
    {
        $form = $this->calculator();
        $form->save();
        $submission = app(FormSubmissionService::class)->submit($form, ['choice' => 'yes']);
        $snapshot = $submission->fresh()->calculation_result;
        $this->assertSame($snapshot, $submission->lead->fresh()->calculation_result);
        $this->assertSame('توضیح ثبت‌شده سرعت', $snapshot['decision_report']['factors'][0]['explanation']);

        $schema = $form->schema;
        $schema['calculator']['decision_report']['explanations']['lsf'][self::IDS[0]] = 'توضیح جدید مدیر';
        $schema['calculator']['recommendations']['lsf'] = 'نتیجه جدید مدیر';
        $schema['calculator']['criteria'][0]['label'] = 'معیار جدید مدیر';
        $schema['calculator']['criterion_scores'] = [];
        $form->update(['schema' => $schema]);
        $submission->refresh();
        $this->assertSame($snapshot, $submission->calculation_result);

        $pdfData = app(CalculatorSubmissionReport::class)->data($submission);
        $this->assertSame($snapshot['decision_report'], $pdfData['weighted']['decision_report']);
        $modal = view('forms._calculator-result-modal', [
            'form' => $form->fresh(), 'calculationResult' => $snapshot, 'modalId' => 'decision-test',
        ])->render();
        $pdf = view('reports.calculator-submission', $pdfData)->render();
        foreach ([$modal, $pdf] as $html) {
            $this->assertStringContainsString('چرا این پیشنهاد برای شما مناسب‌تر است؟', $html);
            $this->assertStringContainsString('توضیح ثبت‌شده سرعت', $html);
            $this->assertStringContainsString(DecisionReportBuilder::FALLBACK_EXPLANATION, $html);
            $this->assertStringNotContainsString('توضیح جدید مدیر', $html);
            $this->assertStringNotContainsString('نتیجه جدید مدیر', $html);
            $this->assertStringNotContainsString('معیار جدید مدیر', $html);
            $this->assertStringContainsString('&lt;script&gt;متن مدیر&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>متن مدیر</script>', $html);
            $this->assertStringNotContainsString('مهم‌ترین عوامل مؤثر در این نتیجه', $html);
        }

        // Historical snapshots without reports keep the original factor-list presentation.
        unset($snapshot['decision_report']);
        $submission->calculation_result = $snapshot;
        $legacyPdf = view('reports.calculator-submission', app(CalculatorSubmissionReport::class)->data($submission))->render();
        $legacyModal = view('forms._calculator-result-modal', [
            'form' => $form->fresh(), 'calculationResult' => $snapshot, 'modalId' => 'legacy-test',
        ])->render();
        foreach ([$legacyModal, $legacyPdf] as $html) {
            $this->assertStringContainsString('مهم‌ترین عوامل مؤثر در این نتیجه', $html);
            $this->assertStringNotContainsString('چرا این پیشنهاد برای شما مناسب‌تر است؟', $html);
            $this->assertStringNotContainsString('توضیح ثبت‌شده سرعت', $html);
            $this->assertStringNotContainsString('توضیح جدید مدیر', $html);
        }
    }

    private function calculator(): Form
    {
        return new Form([
            'name' => 'گزارش وزنی', 'slug' => 'weighted-decision', 'type' => 'calculator',
            'status' => 'draft', 'lead_generation_enabled' => true,
            'schema' => [
                'calculator' => [
                    'scoring_mode' => 'weighted',
                    'criteria' => array_map(fn (string $id, string $label): array => [
                        'id' => $id, 'label' => $label, 'base_weight' => 1,
                    ], self::IDS, ['سرعت', 'هزینه', 'کیفیت', 'اجرا']),
                    'recommendations' => ['lsf' => 'سازه سبک', 'other' => 'گزینه دیگر'],
                    'criterion_scores' => ['lsf' => array_combine(self::IDS, [5, 4, 3, 2]), 'other' => array_fill_keys(self::IDS, 1)],
                    'decision_report' => [
                        'enabled' => true, 'top_factors_count' => 1,
                        'explanations' => [
                            'lsf' => [self::IDS[0] => '  توضیح ثبت‌شده سرعت  ', self::IDS[2] => '<script>متن مدیر</script>'],
                            'other' => [self::IDS[1] => 'توضیح گزینه بازنده'],
                        ],
                    ],
                ],
                'fields' => [
                    ['key' => 'choice', 'label' => 'انتخاب', 'type' => 'radio', 'options' => [
                        ['value' => 'yes', 'label' => 'بله', 'scores' => ['lsf' => 2]],
                    ]],
                    ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0', 'key' => 'limit', 'label' => 'محدودیت', 'type' => 'number'],
                ],
            ],
        ]);
    }
}
