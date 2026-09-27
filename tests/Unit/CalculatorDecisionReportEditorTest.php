<?php

namespace Tests\Unit;

use App\Filament\Resources\FormResource;
use App\Filament\Support\CalculatorDecisionReportEditor;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class CalculatorDecisionReportEditorTest extends TestCase
{
    private const SPEED = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const COST = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    public function test_tab_bindings_follow_ids_and_matrix_context_while_save_prunes_deleted_references(): void
    {
        $editor = Livewire::test(DecisionReportEditorHarness::class, ['initial' => $this->data()])
            ->assertSee('گزارش توضیحی نتیجه')
            ->assertSee('۱ از ۲ توضیح تکمیل شده')
            ->assertSee('امتیاز عملکرد: ۵ از ۵')
            ->assertSee('امتیاز عملکرد: ۰ از ۵')
            ->assertSeeHtml('data.schema.calculator.decision_report.explanations.lsf.'.self::SPEED);

        $editor->set('data.schema.calculator.recommendations.0.label', 'عنوان نتیجه جدید')
            ->set('data.schema.calculator.criteria.0.label', 'عنوان معیار جدید')
            ->set('data.schema.calculator.criterion_scores.lsf.'.self::SPEED, 2)
            ->assertSee('عنوان نتیجه جدید')->assertSee('عنوان معیار جدید')
            ->assertSee('امتیاز عملکرد: ۲ از ۵')
            ->assertSet('data.schema.calculator.decision_report.explanations.lsf.'.self::SPEED, 'توضیح سرعت');

        $editor->set('data.schema.calculator.decision_report.explanations.lsf.'.self::COST, " \t ")
            ->assertSee('۱ از ۲ توضیح تکمیل شده')
            ->set('data.schema.calculator.decision_report.explanations.lsf.'.self::COST, ' هزینه بالاتر ')
            ->assertSee('۲ از ۲ توضیح تکمیل شده')
            ->call('persist')->assertHasNoFormErrors();
        $stored = $editor->get('saved.schema.calculator');
        $this->assertSame(7, $stored['decision_report']['top_factors_count']);
        $this->assertSame('هزینه بالاتر', $stored['decision_report']['explanations']['lsf'][self::COST]);
        $this->assertSame(2, $stored['criterion_scores']['lsf'][self::SPEED]);
        $this->assertSame(['enabled', 'top_factors_count', 'explanations'], array_keys($stored['decision_report']));

        $editor->set('data.schema.calculator.recommendations', [['key' => 'lsf', 'label' => 'عنوان نتیجه جدید']])
            ->set('data.schema.calculator.criteria', [['id' => self::SPEED, 'label' => 'عنوان معیار جدید', 'base_weight' => 0]])
            ->assertDontSeeHtml('data.schema.calculator.decision_report.explanations.concrete.')
            ->assertDontSeeHtml('data.schema.calculator.decision_report.explanations.lsf.'.self::COST)
            ->call('persist')->assertHasNoFormErrors();
        $this->assertSame(['lsf' => [self::SPEED => 'توضیح سرعت']], $editor->get('saved.schema.calculator.decision_report.explanations'));

        $editor->set('data.schema.calculator.recommendations.1', ['key' => 'new_result', 'label' => 'نتیجه تازه'])
            ->set('data.schema.calculator.criteria.1', ['id' => self::COST, 'label' => 'هزینه', 'base_weight' => 0])
            ->assertSeeHtml('data.schema.calculator.decision_report.explanations.new_result.'.self::COST)
            ->set('data.schema.calculator.decision_report.explanations.new_result.'.self::COST, 'توضیح تازه')
            ->call('persist')->assertHasNoFormErrors();
        $this->assertSame('توضیح تازه', $editor->get('saved.schema.calculator.decision_report.explanations.new_result.'.self::COST));
    }

    public function test_disabling_report_and_switching_modes_preserve_all_report_state(): void
    {
        $data = $this->data();
        $expected = $data['schema']['calculator']['decision_report'];
        $expected['enabled'] = false;
        $editor = Livewire::test(DecisionReportEditorHarness::class, ['initial' => $data])
            ->set('data.schema.calculator.decision_report.enabled', false)
            ->assertDontSeeHtml('data.schema.calculator.decision_report.explanations.lsf.'.self::SPEED)
            ->call('persist')->assertHasNoFormErrors();
        $this->assertSame($expected, $editor->get('saved.schema.calculator.decision_report'));

        // Simple mode retains live metadata verbatim, including optional blank editor cells.
        $simpleExpected = $editor->get('data.schema.calculator.decision_report');
        $editor->set('data.schema.calculator.scoring_mode', 'simple')
            ->assertDontSee('گزارش توضیحی نتیجه')
            ->call('persist')->assertHasNoFormErrors();
        $this->assertSame($simpleExpected, $editor->get('saved.schema.calculator.decision_report'));
        $this->assertSame(['enabled', 'top_factors_count', 'explanations'], array_keys($simpleExpected));

        $editor->set('data.schema.calculator.scoring_mode', 'weighted')
            ->set('data.schema.calculator.decision_report.enabled', true)
            ->assertSeeHtml('data.schema.calculator.decision_report.explanations.lsf.'.self::SPEED)
            ->call('persist')->assertHasNoFormErrors();
        $expected['enabled'] = true;
        $this->assertSame($expected, $editor->get('saved.schema.calculator.decision_report'));
    }

    public function test_legacy_weighted_defaults_and_simple_without_report_remain_safe(): void
    {
        $data = $this->data();
        unset($data['schema']['calculator']['decision_report']);
        $editor = Livewire::test(DecisionReportEditorHarness::class, ['initial' => $data])
            ->assertSet('data.schema.calculator.decision_report.enabled', false)
            ->call('persist')->assertHasNoFormErrors();
        $this->assertSame([
            'enabled' => false, 'top_factors_count' => 3, 'explanations' => [],
        ], $editor->get('saved.schema.calculator.decision_report'));

        $data['schema']['calculator']['scoring_mode'] = 'simple';
        $editor = Livewire::test(DecisionReportEditorHarness::class, ['initial' => $data])
            ->assertDontSee('گزارش توضیحی نتیجه')
            ->call('persist')->assertHasNoFormErrors();
        $this->assertArrayNotHasKey('decision_report', $editor->get('saved.schema.calculator'));

        $editor->set('data.schema.calculator.scoring_mode', 'weighted')
            ->call('persist')->assertHasNoFormErrors();
        $this->assertSame([
            'enabled' => false, 'top_factors_count' => 3, 'explanations' => [],
        ], $editor->get('saved.schema.calculator.decision_report'));

        $data['schema']['calculator']['decision_report'] = ['enabled' => true, 'top_factors_count' => 9, 'explanations' => ['lsf' => [self::SPEED => 'متن']]];
        $editor = Livewire::test(DecisionReportEditorHarness::class, ['initial' => $data])
            ->assertDontSee('گزارش توضیحی نتیجه')->call('persist')->assertHasNoFormErrors();
        $this->assertSame($data['schema']['calculator']['decision_report'], $editor->get('saved.schema.calculator.decision_report'));
    }

    private function data(): array
    {
        return ['type' => 'calculator', 'schema' => ['fields' => [], 'calculator' => [
            'scoring_mode' => 'weighted',
            'criteria' => [
                ['id' => self::SPEED, 'label' => 'سرعت', 'base_weight' => 0],
                ['id' => self::COST, 'label' => 'هزینه', 'base_weight' => 0],
            ],
            'recommendations' => ['lsf' => 'سازه سبک', 'concrete' => 'بتن'],
            'criterion_scores' => ['lsf' => [self::SPEED => 5]],
            'decision_report' => [
                'enabled' => true, 'top_factors_count' => 7,
                'explanations' => ['lsf' => [self::SPEED => 'توضیح سرعت'], 'concrete' => [self::COST => 'توضیح هزینه']],
            ],
        ]]];
    }
}

/** Exercise real Filament hydration/dehydration without database setup or the unrelated form editor. */
class DecisionReportEditorHarness extends Component implements HasForms
{
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public function mount(array $initial): void
    {
        $this->form->fill(FormResource::prepareSchemaForEditor($initial));
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Hidden::make('type'),
            Hidden::make('schema.calculator.scoring_mode'),
            Hidden::make('schema.calculator.recommendations'),
            Hidden::make('schema.calculator.criteria'),
            Hidden::make('schema.calculator.criterion_scores'),
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
