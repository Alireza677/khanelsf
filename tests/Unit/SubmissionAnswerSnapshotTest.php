<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Services\SubmissionAnswerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionAnswerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_snapshots_raw_and_display_values_from_the_normalized_form_schema(): void
    {
        $form = $this->form();
        $snapshot = app(SubmissionAnswerSnapshot::class)->answers($form, [
            'project_name' => 'پروژه سپهر',
            'building_type' => 'existing',
            'delivery' => 'fast',
        ]);

        $this->assertSame([
            [
                'field_key' => 'project_name',
                'field_label' => 'نام پروژه',
                'raw_value' => 'پروژه سپهر',
                'display_value' => 'پروژه سپهر',
            ],
            [
                'field_key' => 'building_type',
                'field_label' => 'نوع پروژه',
                'raw_value' => 'existing',
                'display_value' => 'بنای موجود',
            ],
            [
                'field_key' => 'delivery',
                'field_label' => 'زمان تحویل',
                'raw_value' => 'fast',
                'display_value' => 'تحویل سریع',
            ],
        ], $snapshot);
    }

    public function test_it_adds_stable_field_recommendation_and_score_labels_to_calculation_snapshot(): void
    {
        $form = $this->form();
        $service = app(SubmissionAnswerSnapshot::class);
        $answers = $service->answers($form, ['building_type' => 'existing']);
        $result = $service->enrichCalculationResult($form, [
            'recommended_method' => 'prefabricated',
            'result' => 'پیش ساخته',
            'scores' => ['prefabricated' => 3, 'onsite' => 1],
        ], $answers);

        $this->assertSame(['building_type' => 'نوع پروژه'], $result['answer_field_labels']);
        $this->assertSame([
            'prefabricated' => 'پیش ساخته',
            'onsite' => 'ساخت در محل',
        ], $result['recommendation_labels']);
        $this->assertSame($result['recommendation_labels'], $result['score_labels']);
        $this->assertSame(['prefabricated' => 3, 'onsite' => 1], $result['scores']);
    }

    private function form(): Form
    {
        return Form::query()->create([
            'name' => 'Snapshot Test',
            'slug' => 'snapshot-test',
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'calculator',
            'calculator_identifier' => 'snapshot_test',
            'schema_version' => 2,
            'schema' => [
                'fields' => [
                    ['key' => 'project_name', 'label' => 'نام پروژه', 'type' => 'text'],
                    [
                        'key' => 'building_type',
                        'label' => 'نوع پروژه',
                        'type' => 'image_choice',
                        'options' => [[
                            'value' => 'existing',
                            'label' => 'بنای موجود',
                            'scores' => ['prefabricated' => 3],
                        ]],
                    ],
                    [
                        'key' => 'delivery',
                        'label' => 'زمان تحویل',
                        'type' => 'select',
                        'options' => [[
                            'value' => 'fast',
                            'label' => 'تحویل سریع',
                        ]],
                    ],
                ],
                'calculator' => ['recommendations' => [
                    'prefabricated' => 'پیش ساخته',
                    'onsite' => 'ساخت در محل',
                ]],
            ],
        ]);
    }
}
