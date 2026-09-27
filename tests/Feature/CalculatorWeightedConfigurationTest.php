<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Calculators\CalculatorScoringSchema;
use App\Services\Calculators\InvalidCalculatorConfiguration;
use App\Services\CalculatorSubmissionReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CalculatorWeightedConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_matrix_saves_and_failed_saves_preserve_values_through_renaming_and_reordering(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $contract = app(CalculatorScoringSchema::class);
        $speed = $contract->newCriterion('سرعت اجرا');
        $cost = $contract->newCriterion('هزینه');
        $id = $speed['id'];
        $form = Form::query()->create([
            'name' => 'ماتریس وزنی', 'slug' => 'weighted-matrix', 'status' => 'draft',
            'type' => 'calculator', 'calculator_identifier' => 'weighted_matrix',
            'schema' => ['fields' => [], 'calculator' => [
                'scoring_mode' => 'weighted', 'criteria' => [$speed, $cost],
                'recommendations' => ['steel' => 'سازه فولادی', 'concrete' => 'بتن'],
            ]],
        ]);
        $editor = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $zeros = array_fill_keys(['steel', 'concrete'], [$id => 0, $cost['id'] => 0]);
        foreach ([null, '', [], ['steel' => [$id => null, $cost['id'] => '']]] as $matrix) {
            $editor->set('data.schema.calculator.criterion_scores', $matrix)
                ->call('save')->assertHasNoFormErrors();
            $this->assertSame($zeros, $form->fresh()->schema['calculator']['criterion_scores']);
        }

        $matrix = ['steel' => [$id => '5'], 'concrete' => [$cost['id'] => 0]];
        $editor->set('data.schema.calculator.criterion_scores', $matrix)
            ->call('save')->assertHasNoFormErrors();
        $expected = $zeros;
        $expected['steel'][$id] = 5;
        $this->assertSame($expected, $form->fresh()->schema['calculator']['criterion_scores']);

        // Both ordinary field validation and schema validation must leave live state intact.
        $editor->set('data.name', '')->call('save')->assertHasFormErrors(['name']);
        $this->assertEquals(5, $editor->get("data.schema.calculator.criterion_scores.steel.{$id}"));
        $editor->set('data.name', 'ماتریس وزنی');
        foreach ([5.1, -1, 'invalid'] as $value) {
            $editor->set("data.schema.calculator.criterion_scores.concrete.{$id}", $value)
                ->call('save')->assertHasFormErrors(["schema.calculator.criterion_scores.concrete.{$id}"]);
            $this->assertEquals(5, $editor->get("data.schema.calculator.criterion_scores.steel.{$id}"));
            $this->assertSame($value, $editor->get("data.schema.calculator.criterion_scores.concrete.{$id}"));
            $this->assertSame($expected, $form->fresh()->schema['calculator']['criterion_scores']);
        }
        $editor->set("data.schema.calculator.criterion_scores.concrete.{$id}", '');
        $criteriaKeys = array_keys($editor->get('data.schema.calculator.criteria'));
        $resultKeys = array_keys($editor->get('data.schema.calculator.recommendations'));
        // A hidden ID fails in prepareSchemaForStorage, after Filament dehydration.
        $editor->set("data.schema.calculator.criteria.{$criteriaKeys[1]}.id", 'invalid')
            ->call('save')->assertHasFormErrors(['schema.calculator.criteria.1.id']);
        $this->assertEquals(5, $editor->get("data.schema.calculator.criterion_scores.steel.{$id}"));
        $editor->assertSeeHtml("data.schema.calculator.criterion_scores.steel.{$id}")
            ->set("data.schema.calculator.criteria.{$criteriaKeys[1]}.id", $cost['id']);
        $editor->set("data.schema.calculator.criteria.{$criteriaKeys[0]}.label", 'سرعت جدید')
            ->callFormComponentAction('schema.calculator.criteria', 'reorder', arguments: ['items' => array_reverse($criteriaKeys)])
            ->set("data.schema.calculator.recommendations.{$resultKeys[0]}.label", 'فولاد جدید')
            ->callFormComponentAction('schema.calculator.recommendations', 'reorder', arguments: ['items' => array_reverse($resultKeys)])
            ->call('save')->assertHasNoFormErrors();
        $stored = $form->fresh()->schema['calculator'];
        $this->assertSame([$cost['id'], $id], array_column($stored['criteria'], 'id'));
        $this->assertSame(['concrete', 'steel'], array_keys($stored['recommendations']));
        $this->assertSame(5, $stored['criterion_scores']['steel'][$id]);
        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSet("data.schema.calculator.criterion_scores.steel.{$id}", 5);

        $editor->callFormComponentAction('schema.calculator.criteria', 'add');
        $criteria = $editor->get('data.schema.calculator.criteria');
        $newCriterionKey = array_key_last($criteria);
        $newId = $criteria[$newCriterionKey]['id'];
        $editor->set("data.schema.calculator.criteria.{$newCriterionKey}.label", 'کیفیت')
            ->callFormComponentAction('schema.calculator.recommendations', 'add');
        $newResultKey = array_key_last($editor->get('data.schema.calculator.recommendations'));
        $editor->set("data.schema.calculator.recommendations.{$newResultKey}.label", 'نتیجه جدید')
            ->call('save')->assertHasNoFormErrors();
        $stored = $form->fresh()->schema['calculator'];
        $this->assertSame(5, $stored['criterion_scores']['steel'][$id]);
        $this->assertSame(0, $stored['criterion_scores']['steel'][$newId]);
        $this->assertSame(
            [$cost['id'] => 0, $id => 0, $newId => 0],
            $stored['criterion_scores'][array_key_last($stored['recommendations'])],
        );
    }

    public function test_reactive_criteria_matrix_and_answer_effects_keep_stable_references(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = Form::query()->create(FormResource::prepareSchemaForStorage([
            'name' => 'فرم قدیمی', 'slug' => 'weighted-editor', 'status' => 'draft',
            'type' => 'calculator', 'calculator_identifier' => 'weighted_editor',
            'schema' => [
                'calculator' => ['recommendations' => ['lsf' => 'سازه سبک', 'concrete' => 'بتن']],
                'fields' => [[
                    'key' => 'priority', 'label' => 'اولویت', 'type' => 'radio',
                    'options' => [['value' => 'speed', 'label' => 'اجرای سریع', 'scores' => ['lsf' => 3]]],
                ]],
            ],
        ]));
        $editor = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $this->assertSame('simple', $editor->get('data.schema.calculator.scoring_mode'));
        $this->assertStringNotContainsString('ماتریس امتیاز نتایج', $editor->html());
        $editor->set('data.schema.calculator.scoring_mode', 'weighted')
            ->callFormComponentAction('schema.calculator.criteria', 'add');
        $criteria = $editor->get('data.schema.calculator.criteria');
        $firstKey = array_key_first($criteria);
        $firstId = $criteria[$firstKey]['id'];
        $editor->set("data.schema.calculator.criteria.{$firstKey}.label", 'سرعت اجرا')
            ->callFormComponentAction('schema.calculator.criteria', 'add');
        $criteria = $editor->get('data.schema.calculator.criteria');
        $secondKey = array_key_last($criteria);
        $secondId = $criteria[$secondKey]['id'];
        $editor->set("data.schema.calculator.criteria.{$secondKey}.label", 'هزینه')
            ->set("data.schema.calculator.criterion_scores.lsf.{$firstId}", 5)
            ->set("data.schema.calculator.criterion_scores.concrete.{$firstId}", 2)
            ->set("data.schema.calculator.criterion_scores.lsf.{$secondId}", '');

        $fields = $editor->get('data.schema.fields');
        $fieldKey = array_key_first($fields);
        $optionKey = array_key_first($fields[$fieldKey]['options']);
        $effectPath = "schema.fields.{$fieldKey}.options.{$optionKey}.criterion_weights";
        $editor->callFormComponentAction($effectPath, 'add');
        $effectKey = array_key_first($editor->get("data.{$effectPath}"));
        $editor->set("data.{$effectPath}.{$effectKey}.criterion_id", $firstId)
            ->set("data.{$effectPath}.{$effectKey}.weight", 11)
            ->call('save')->assertHasFormErrors(["{$effectPath}.{$effectKey}.weight"])
            ->set("data.{$effectPath}.{$effectKey}.weight", 10)
            ->set("data.schema.calculator.criteria.{$firstKey}.label", 'سرعت جدید')
            ->callFormComponentAction('schema.calculator.criteria', 'reorder', arguments: ['items' => [$secondKey, $firstKey]])
            ->set('data.schema.calculator.scoring_mode', 'simple')
            ->call('save')->assertHasNoFormErrors();
        $stored = $form->fresh()->schema;
        $this->assertSame([$secondId, $firstId], array_column($stored['calculator']['criteria'], 'id'));
        $this->assertSame('سرعت جدید', $stored['calculator']['criteria'][1]['label']);
        $this->assertSame([$firstId => 10], $stored['fields'][0]['options'][0]['criterion_weights']);
        $this->assertSame(['lsf' => 3], $stored['fields'][0]['options'][0]['scores']);
        $this->assertSame([$secondId => 0, $firstId => 5], $stored['calculator']['criterion_scores']['lsf']);

        $editor = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->set('data.schema.calculator.scoring_mode', 'weighted');
        $criteria = $editor->get('data.schema.calculator.criteria');
        $firstKey = array_key_last($criteria);
        $editor->set("data.schema.calculator.criteria.{$firstKey}.base_weight", 11)
            ->call('save')->assertHasFormErrors(["schema.calculator.criteria.{$firstKey}.base_weight"])
            ->set("data.schema.calculator.criteria.{$firstKey}.base_weight", 0)
            ->callFormComponentAction('schema.calculator.criteria', 'delete', arguments: ['item' => $firstKey])
            ->call('save')->assertHasNoFormErrors();
        $stored = $form->fresh()->schema;
        $this->assertSame([$secondId], array_column($stored['calculator']['criteria'], 'id'));
        $this->assertSame([], $stored['fields'][0]['options'][0]['criterion_weights']);
        $this->assertSame([$secondId => 0], $stored['calculator']['criterion_scores']['lsf']);

        // Stale and malformed references in imported data must not break editor hydration.
        $stored['fields'][0]['options'][0]['criterion_weights'] = [$firstId => 4, 'invalid.id' => 5];
        $stored['calculator']['criterion_scores']['lsf'] = [$firstId => 3, 'invalid.id' => 2];
        $form->update(['schema' => $stored]);
        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame([], $form->fresh()->schema['fields'][0]['options'][0]['criterion_weights']);
    }

    public function test_editor_preserves_weighted_metadata_and_submission_uses_the_weighted_engine(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $criterion = app(CalculatorScoringSchema::class)->newCriterion('سرعت');
        $id = $criterion['id'];
        $form = Form::query()->create(FormResource::prepareSchemaForStorage([
            'name' => 'محاسبه‌گر وزنی', 'slug' => 'weighted-config', 'status' => 'published',
            'type' => 'calculator', 'calculator_identifier' => 'weighted_config',
            'schema' => [
                'fields' => [[
                    'key' => 'priority', 'label' => 'اولویت', 'type' => 'radio', 'required' => true,
                    'options' => [['value' => 'speed', 'label' => 'سرعت', 'scores' => ['lsf' => -2.5], 'criterion_weights' => [$id => 8]]],
                ]],
                'calculator' => [
                    'scoring_mode' => 'weighted', 'criteria' => [$criterion],
                    'recommendations' => ['lsf' => 'سازه سبک'], 'criterion_scores' => ['lsf' => [$id => 5]],
                ],
            ],
        ]));

        $editor = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('روش محاسبه')
            ->call('save')
            ->assertHasNoFormErrors();
        foreach (['simple', 'weighted'] as $mode) {
            $editor->set('data.schema.calculator.scoring_mode', $mode)->call('save')->assertHasNoFormErrors();
            $stored = $form->fresh()->schema;
            $this->assertSame($mode, $stored['calculator']['scoring_mode']);
            $this->assertSame([$criterion], $stored['calculator']['criteria']);
            $this->assertSame(['lsf' => [$id => 5]], $stored['calculator']['criterion_scores']);
            $this->assertSame([$id => 8], $stored['fields'][0]['options'][0]['criterion_weights']);
            $this->assertSame(['lsf' => -2.5], $stored['fields'][0]['options'][0]['scores']);
        }

        $editor->set('data.schema.calculator.criterion_scores', ['lsf' => [$id => 6]])
            ->call('save')->assertHasFormErrors(["schema.calculator.criterion_scores.lsf.{$id}"]);
        $this->assertSame(['lsf' => [$id => 5]], $form->fresh()->schema['calculator']['criterion_scores']);

        $this->from(route('forms.show', $form->slug))
            ->post(route('forms.submit', $form->slug), ['priority' => 'speed', '_form_instance' => 'weighted_preview'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('calculator_result_instances.weighted_preview.calculation_result.suitability_percentages.lsf', '100.00');
        $this->assertDatabaseCount('form_submissions', 1);
        $submission = FormSubmission::query()->firstOrFail();
        $snapshot = $submission->calculation_result;
        $this->assertSame('40', $snapshot['scores']['lsf']);
        $changed = $form->fresh()->schema;
        $changed['calculator']['criteria'][0]['label'] = 'عنوان تغییرکرده';
        $changed['calculator']['criterion_scores']['lsf'][$id] = 0;
        $form->update(['schema' => $changed]);
        $html = view('forms._calculator-result-modal', [
            'form' => $form->fresh(), 'calculationResult' => $snapshot, 'modalId' => 'weighted-result',
        ])->render();
        $this->assertStringContainsString('پیشنهاد اصلی بر اساس پاسخ‌های شما', $html);
        $this->assertStringContainsString('میزان تطابق: ۱۰۰٪', $html);
        $this->assertStringContainsString('مهم‌ترین عوامل مؤثر در این نتیجه', $html);
        $reportData = app(CalculatorSubmissionReport::class)->data($submission->fresh());
        $this->assertSame('۱۰۰٪', $reportData['weighted']['suitability_label']);
        $this->assertSame('سرعت', $reportData['weighted']['factors'][0]['label']);
        $this->assertStringContainsString('میزان تطابق: ۱۰۰٪', view('reports.calculator-submission', $reportData)->render());
        $this->assertSame($snapshot, $submission->fresh()->calculation_result);
        $changed['fields'][0]['options'][0]['criterion_weights'][$id] = 0;
        $form->schema = $changed;
        $noScore = app(\App\Services\Calculators\CalculatorManager::class)->calculate($form, ['priority' => 'speed'])->toArray();
        $noScoreHtml = view('forms._calculator-result-modal', ['form' => $form, 'calculationResult' => $noScore, 'modalId' => 'no-score'])->render();
        $this->assertStringContainsString('امتیازی برای مقایسه محاسبه نشد', $noScoreHtml);
        $this->assertStringNotContainsString('پیشنهاد نهایی', $noScoreHtml);
    }

    public function test_invalid_weighted_configuration_is_logged_and_returns_persian_feedback_without_submission(): void
    {
        Log::spy();
        $criterion = app(CalculatorScoringSchema::class)->newCriterion('سرعت');
        $form = Form::query()->create([
            'name' => 'فرم آزمایشی', 'slug' => 'invalid-weighted', 'status' => 'published', 'type' => 'calculator',
            'calculator_identifier' => 'invalid_weighted', 'schema' => [
                'calculator' => ['scoring_mode' => 'weighted', 'criteria' => [$criterion], 'recommendations' => ['lsf' => 'سبک']],
                'fields' => [['key' => 'choice', 'label' => 'انتخاب', 'type' => 'radio', 'options' => [[
                    'value' => 'one', 'label' => 'یک', 'criterion_weights' => ['invalid.id' => 11],
                ]]]],
            ],
        ]);
        $this->get(route('forms.show', $form->slug))->assertOk();
        $this->from(route('forms.show', $form->slug))
            ->post(route('forms.submit', $form->slug), ['choice' => 'one', '_form_instance' => 'invalid_weighted'])
            ->assertRedirect(route('forms.show', $form->slug))
            ->assertSessionHasErrors(['calculator' => InvalidCalculatorConfiguration::PUBLIC_MESSAGE], errorBag: 'form_'.substr(hash('sha256', 'invalid_weighted'), 0, 24));
        $this->assertDatabaseCount('form_submissions', 0);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context): bool => str_contains($message, 'تنظیمات محاسبه‌گر معتبر نیست') && $context['form_id'] === $form->getKey())->once();
    }
}
