<?php

namespace App\Filament\Forms\Components;

use App\Filament\Support\CalculatorWeightedEditor;
use App\Services\Calculators\CalculatorScoringSchema;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;

class CalculatorPerformanceMatrix extends Field
{
    protected string $view = 'filament.forms.components.calculator-performance-matrix';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([])->hiddenLabel()->dehydratedWhenHidden();
        $normalize = fn (mixed $state): array => app(CalculatorScoringSchema::class)->normalizePerformanceMatrix(
            $state, $this->getCriteria(), $this->getResults(), $this->getStatePath(),
        );
        // Validation runs before dehydration. Transform copies at both boundaries;
        // never refill or overwrite Livewire state when a save can still fail.
        $this->mutateStateForValidationUsing($normalize);
        $this->mutateDehydratedStateUsing($normalize);
        $this->schema(function (): array {
            $cells = [];
            foreach ($this->getCriteria() as $id => $criterionLabel) {
                foreach ($this->getResults() as $key => $resultLabel) {
                    $message = CalculatorScoringSchema::performanceMessage($criterionLabel, $resultLabel);
                    $cells[] = TextInput::make("{$key}.{$id}")
                        ->label("{$criterionLabel}، {$resultLabel}")
                        ->hiddenLabel()
                        ->numeric()->minValue(0)->maxValue(5)->step('any')
                        ->live(onBlur: true)
                        ->placeholder('۰')
                        ->extraInputAttributes(['aria-label' => "{$criterionLabel}، {$resultLabel}", 'dir' => 'ltr'])
                        ->validationMessages([
                            'numeric' => $message,
                            'min' => $message,
                            'max' => $message,
                        ]);
                }
            }

            return $cells;
        });
    }

    public function getCriteria(): array
    {
        return CalculatorWeightedEditor::criteriaOptions(data_get($this->getLivewire(), 'data.schema.calculator.criteria', []));
    }

    public function getResults(): array
    {
        return CalculatorWeightedEditor::resultOptions(data_get($this->getLivewire(), 'data.schema.calculator.recommendations', []));
    }
}
