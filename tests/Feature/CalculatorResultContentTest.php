<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Support\CalculatorDecisionReportEditor;
use App\Filament\Support\CalculatorResultContentEditor;
use App\Models\Form;
use App\Services\Calculators\CalculatorManager;
use App\Services\Calculators\CalculatorResultContent;
use App\Services\CalculatorSubmissionReport;
use App\Services\FormSubmissionService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form as EditorForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class CalculatorResultContentTest extends TestCase
{
    use RefreshDatabase;

    private const CRITERION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_simple_editor_saves_loads_and_follows_stable_keys_when_labels_change(): void
    {
        $initial = $this->definition();
        unset($initial['schema']['calculator']['result_content']);
        $editor = Livewire::test(ResultContentEditorHarness::class, ['initial' => $initial])
            ->assertSee('محتوای نتایج')
            ->assertSeeHtml('data.schema.calculator.result_content.prefab.result_title');
        foreach ($this->texts() as $field => $text) {
            $editor->set("data.schema.calculator.result_content.prefab.{$field}", " {$text} ");
        }
        $editor->set('data.schema.calculator.recommendations.0.label', 'عنوان جدید گزینه')
            ->assertSee('عنوان جدید گزینه')
            ->call('persist')->assertHasNoFormErrors();

        $form = Form::create($editor->get('saved'));
        $stored = $form->fresh()->schema;
        $this->assertSame($this->texts(), $stored['calculator']['result_content']['prefab']);
        $this->assertSame('عنوان جدید گزینه', $stored['calculator']['recommendations']['prefab']);
        $reopened = Livewire::test(ResultContentEditorHarness::class, ['initial' => $form->fresh()->toArray()]);
        foreach ($this->texts() as $field => $text) {
            $reopened->assertSet("data.schema.calculator.result_content.prefab.{$field}", $text);
        }

        // A newly added canonical result gets its own controls without redefining the result.
        $reopened->set('data.schema.calculator.recommendations.2', ['key' => 'new_result', 'label' => 'نتیجه تازه'])
            ->assertSeeHtml('data.schema.calculator.result_content.new_result.result_title')
            ->set('data.schema.calculator.result_content.new_result.result_title', 'عنوان نتیجه تازه')
            ->call('persist')->assertHasNoFormErrors();
        $this->assertSame('عنوان نتیجه تازه', $reopened->get('saved.schema.calculator.result_content.new_result.result_title'));
        $this->assertSame($this->texts(), $reopened->get('saved.schema.calculator.result_content.prefab'));
    }

    public function test_switching_methods_and_reopening_preserves_static_content_and_weighted_report(): void
    {
        $initial = $this->definition('weighted');
        $expected = $initial['schema']['calculator'];
        foreach (['simple', 'weighted', 'simple'] as $mode) {
            $editor = Livewire::test(ResultContentEditorHarness::class, ['initial' => $initial]);
            // The legacy editor retains optional blank explanation cells verbatim in simple mode.
            $reportBefore = $editor->get('data.schema.calculator.decision_report');
            $editor->set('data.schema.calculator.scoring_mode', $mode)
                ->assertSee('محتوای نتایج')
                ->call('persist')->assertHasNoFormErrors();
            $initial = $editor->get('saved');
            $this->assertSame($expected['result_content'], $initial['schema']['calculator']['result_content']);
            $this->assertSame($mode === 'simple' ? $reportBefore : $expected['decision_report'], $initial['schema']['calculator']['decision_report']);
        }
    }

    public function test_both_methods_snapshot_and_render_only_winner_content_without_changing_calculation(): void
    {
        foreach (['simple', 'weighted'] as $mode) {
            $definition = $this->definition($mode);
            $schema = $definition['schema'];
            unset($definition['schema']['calculator']['result_content']);
            $form = new Form($definition);
            $legacy = app(CalculatorManager::class)->calculate($form, ['choice' => 'yes'])->toArray();
            foreach (CalculatorResultContent::FIELDS as $field) {
                $this->assertArrayNotHasKey($field, $legacy);
            }
            $form->schema = $schema;
            $form->save();
            $result = app(CalculatorManager::class)->calculate($form, ['choice' => 'yes'])->toArray();
            $this->assertSame($legacy, array_diff_key($result, array_flip(CalculatorResultContent::FIELDS)));
            $this->assertSame('prefab', $result['recommended_method']);
            foreach ($this->texts() as $field => $text) {
                $this->assertSame($text, $result[$field]);
            }
            $submission = app(FormSubmissionService::class)->submit($form, ['choice' => 'yes']);
            $snapshot = $submission->fresh()->calculation_result;
            $this->assertSame($snapshot, $submission->lead->fresh()->calculation_result);

            // Rendering must use the submitted content, never the subsequently edited schema.
            $schema['calculator']['result_content']['prefab'] = ['result_title' => 'محتوای جدید مدیر'];
            $form->update(['schema' => $schema]);
            $modal = view('forms._calculator-result-modal', [
                'form' => $form, 'calculationResult' => $snapshot, 'modalId' => 'content-test',
            ])->render();
            $pdf = view('reports.calculator-submission', app(CalculatorSubmissionReport::class)->data($submission->fresh()))->render();
            foreach ([$modal, $pdf] as $html) {
                foreach ($this->texts() as $text) {
                    $this->assertStringContainsString(e($text), $html);
                }
                $this->assertStringNotContainsString('<script>یادداشت</script>', $html);
                $this->assertStringNotContainsString('محتوای جدید مدیر', $html);
                $this->assertStringNotContainsString('محتوای نتیجه بازنده', $html);
                if ($mode === 'weighted') {
                    $this->assertStringContainsString('توضیح وزنی قدیمی', $html);
                }
            }
        }
    }

    public function test_no_winner_produces_no_static_content(): void
    {
        foreach (['simple', 'weighted'] as $mode) {
            $definition = $this->definition($mode);
            $definition['schema']['calculator']['eligibility_rules'] = [[
                'rule_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
                'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                'operator' => 'equals', 'number_value' => 1,
                'profiles' => ['prefab', 'onsite'], 'effect' => 'exclude', 'reason' => 'محدودیت',
            ]];
            $result = app(CalculatorManager::class)->calculate(new Form($definition), ['choice' => 'yes', 'limit' => 1])->toArray();
            $this->assertTrue($result['no_eligible_recommendation']);
            foreach (CalculatorResultContent::FIELDS as $field) {
                $this->assertArrayNotHasKey($field, $result);
            }
        }
        $definition = $this->definition('weighted');
        $definition['schema']['calculator']['criteria'][0]['base_weight'] = 0;
        $result = app(CalculatorManager::class)->calculate(new Form($definition), ['choice' => 'yes'])->toArray();
        $this->assertTrue($result['no_score']);
        foreach (CalculatorResultContent::FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $result);
        }
    }

    private function texts(): array
    {
        return [
            'result_title' => 'پیشنهاد پیش‌ساخته', 'result_summary' => 'خلاصه نتیجه برنده',
            'result_description' => "توضیحات نتیجه\nخط دوم", 'result_note' => '<script>یادداشت</script>',
        ];
    }

    private function definition(string $mode = 'simple'): array
    {
        return [
            'name' => 'محتوای نتیجه', 'slug' => "result-content-{$mode}", 'type' => 'calculator',
            'status' => 'draft', 'lead_generation_enabled' => true,
            'schema' => [
                'fields' => [
                    ['key' => 'choice', 'type' => 'radio', 'label' => 'انتخاب', 'options' => [
                        ['value' => 'yes', 'label' => 'بله', 'scores' => ['prefab' => 2, 'onsite' => 1]],
                    ]],
                    ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'key' => 'limit', 'label' => 'محدودیت', 'type' => 'number'],
                ],
                'calculator' => [
                    'scoring_mode' => $mode,
                    'recommendations' => ['prefab' => 'پیش‌ساخته', 'onsite' => 'مونتاژ در سایت'],
                    'criteria' => [['id' => self::CRITERION, 'label' => 'سرعت', 'base_weight' => 1]],
                    'criterion_scores' => ['prefab' => [self::CRITERION => 5], 'onsite' => [self::CRITERION => 1]],
                    'decision_report' => ['enabled' => true, 'top_factors_count' => 3, 'explanations' => [
                        'prefab' => [self::CRITERION => 'توضیح وزنی قدیمی'],
                    ]],
                    'result_content' => ['prefab' => $this->texts(), 'onsite' => ['result_title' => 'محتوای نتیجه بازنده']],
                ],
            ],
        ];
    }
}

/** Real Filament state hydration and save adapters, without the unrelated editor controls. */
class ResultContentEditorHarness extends Component implements HasForms
{
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public function mount(array $initial): void
    {
        $this->form->fill(FormResource::prepareSchemaForEditor($initial));
    }

    public function form(EditorForm $form): EditorForm
    {
        return $form->statePath('data')->schema([
            Hidden::make('name'), Hidden::make('slug'), Hidden::make('type'), Hidden::make('status'),
            Hidden::make('lead_generation_enabled'), Hidden::make('schema.fields'),
            Hidden::make('schema.calculator.scoring_mode'),
            Hidden::make('schema.calculator.recommendations'),
            Hidden::make('schema.calculator.criteria'),
            Hidden::make('schema.calculator.criterion_scores'),
            CalculatorResultContentEditor::section(),
            CalculatorDecisionReportEditor::section(),
        ]);
    }

    public function persist(): void
    {
        $this->saved = FormResource::prepareSchemaForStorage($this->form->getState());
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
