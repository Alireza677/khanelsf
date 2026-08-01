<?php

namespace App\Services\Calculators;

final readonly class CalculationResult
{
    public function __construct(
        public string $calculatorIdentifier,
        public array $answers,
        public array $answerLabels,
        public string $recommendedMethod,
        public string $result,
        public array $scores,
    ) {}

    public function toArray(): array
    {
        return [
            'calculator_identifier' => $this->calculatorIdentifier,
            'answers' => $this->answers,
            'answer_labels' => $this->answerLabels,
            'recommended_method' => $this->recommendedMethod,
            'result' => $this->result,
            'scores' => $this->scores,
        ];
    }
}
