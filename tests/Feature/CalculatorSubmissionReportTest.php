<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Services\CalculatorSubmissionReport;
use App\Services\SubmissionAnswerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CalculatorSubmissionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_report_download_uses_the_stored_snapshot_without_recalculating(): void
    {
        $submission = $this->submission();

        $url = URL::temporarySignedRoute(
            'forms.submissions.calculator-report',
            now()->addMinutes(5),
            ['submission' => $submission],
        );

        $response = $this->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("calculator-report-{$submission->getKey()}.pdf");

        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('Vazirmatn', $content);
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'Vazirmatn'));
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0(?:\.0+)? 0(?:\.0+)? 595\.28\d* 841\.89\d*\]/', $content);
        $this->assertNotEmpty(glob(storage_path('fonts/vazirmatn_normal_*.ufm')));
        $this->assertNotEmpty(glob(storage_path('fonts/vazirmatn_bold_*.ufm')));
    }

    public function test_report_download_rejects_unsigned_requests_and_normal_submissions(): void
    {
        $submission = $this->submission();

        $this->get(route('forms.submissions.calculator-report', $submission))->assertForbidden();

        $normalSubmission = $submission->replicate(['calculation_result']);
        $normalSubmission->calculation_result = null;
        $normalSubmission->save();

        $url = URL::temporarySignedRoute(
            'forms.submissions.calculator-report',
            now()->addMinutes(5),
            ['submission' => $normalSubmission],
        );

        $this->get($url)->assertNotFound();
    }

    public function test_report_data_remains_snapshot_driven_after_the_form_schema_changes(): void
    {
        $submission = $this->submission();
        $report = app(CalculatorSubmissionReport::class);
        $before = $report->data($submission);

        $submission->form->update([
            'schema' => ['fields' => [[
                'name' => 'completely_new_field',
                'label' => 'متن جدید مدیر',
                'type' => 'text',
            ]]],
        ]);

        $after = $report->data($submission->fresh());

        $this->assertSame($before['customer'], $after['customer']);
        $this->assertSame($before['inputs'], $after['inputs']);
        $this->assertSame($before['recommendation'], $after['recommendation']);
        $this->assertSame($before['scores'], $after['scores']);
        $this->assertSame('پیش ساخته', $after['recommendation']);
        $this->assertSame('تک واحد', $after['inputs'][0]['value']);
        $this->assertSame('پیش ساخته', $after['scores'][0]['label']);
        $this->assertSame('گزینه پیشنهادی 2', $after['scores'][1]['label']);
    }

    public function test_report_prefers_stored_human_readable_answer_and_score_snapshots(): void
    {
        $submission = $this->submission();
        $payload = $submission->payload;
        $payload[SubmissionAnswerSnapshot::PAYLOAD_KEY] = [[
            'field_key' => 'project_volume',
            'field_label' => 'نوع پروژه',
            'raw_value' => 'existing_building',
            'display_value' => 'بنای موجود',
        ]];
        $result = $submission->calculation_result;
        $result['score_labels'] = [
            'prefabricated' => 'پیش ساخته',
            'site_assembly' => 'مونتاژ در سایت',
        ];
        $submission->update(['payload' => $payload, 'calculation_result' => $result]);

        $submission->form->update(['schema' => ['fields' => []]]);
        $data = app(CalculatorSubmissionReport::class)->data($submission->fresh());

        $this->assertSame([
            ['label' => 'نوع پروژه', 'value' => 'بنای موجود'],
        ], $data['inputs']);
        $this->assertSame('پیش ساخته', $data['scores'][0]['label']);
        $this->assertSame('مونتاژ در سایت', $data['scores'][1]['label']);
    }

    public function test_calculator_submission_exposes_a_temporary_pdf_link_in_the_result_experience(): void
    {
        $form = $this->form();

        $this->post(route('forms.submit', $form->slug), [
            'name' => 'مشتری تست',
            'project_type' => 'villa',
        ])
            ->assertRedirect()
            ->assertSessionHas('calculator_result_state.report_url');

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertSee('دریافت گزارش PDF')
            ->assertSee('calculator-report', false);
    }

    private function submission(): FormSubmission
    {
        $form = $this->form();
        $submission = $form->submissions()->create([
            'source' => 'website',
            'payload' => [
                'name' => 'علی رضایی',
                'phone' => '09120000000',
                'project_volume' => 'single_unit',
            ],
            'calculation_result' => [
                'calculator_identifier' => 'construction_process_v1',
                'answers' => ['project_volume' => 'single_unit'],
                'answer_labels' => ['project_volume' => 'تک واحد'],
                'recommended_method' => 'prefabricated',
                'result' => 'پیش ساخته',
                'scores' => [
                    'prefabricated' => 12,
                    'site_assembly' => 7,
                ],
            ],
            'submitted_at' => now(),
        ]);

        Lead::query()->create([
            'form_submission_id' => $submission->getKey(),
            'form_id' => $form->getKey(),
            'name' => 'علی رضایی',
            'phone' => '09120000000',
            'calculation_result' => $submission->calculation_result,
            'status' => 'new',
            'source' => 'website',
        ]);

        return $submission->load('form', 'lead');
    }

    private function form(): Form
    {
        return Form::query()->create([
            'name' => 'محاسبه‌گر ساخت',
            'slug' => 'calculator-report-test-'.Form::query()->count(),
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'calculator',
            'calculator_identifier' => 'construction_process_v1',
            'schema_version' => 2,
            'schema' => [
                'fields' => [[
                    'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                    'name' => 'name',
                    'label' => 'نام',
                    'type' => 'text',
                    'required' => true,
                ], [
                    'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                    'name' => 'project_type',
                    'label' => 'نوع پروژه',
                    'type' => 'image_choice',
                    'required' => true,
                    'options' => [[
                        'option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
                        'value' => 'villa',
                        'label' => 'ویلا',
                        'scores' => ['prefabricated' => 3],
                    ]],
                ]],
                'calculator' => ['recommendations' => ['prefabricated' => 'پیش ساخته']],
            ],
        ]);
    }
}
