<?php

namespace App\Services\Calculators;

use App\Models\Form;
use App\Services\FormSchema;
use InvalidArgumentException;

final class CalculatorManager
{
    public function __construct(private readonly FormSchema $schema) {}

    public function calculate(Form $form, array $payload): CalculationResult
    {
        if (! $form->isCalculator()) {
            throw new InvalidArgumentException('Only calculator forms can be scored.');
        }

        $recommendations = $this->recommendations($form);
        $scores = array_fill_keys(array_keys($recommendations), 0);
        $answers = [];
        $answerLabels = [];

        foreach ($this->schema->fields($form) as $field) {
            if (! in_array($field['type'], ['image_choice', 'radio_card'], true)) {
                continue;
            }

            $answer = $payload[$field['name']] ?? null;
            $option = collect($field['options'])->firstWhere('value', $answer);

            if (! is_string($answer) || ! is_array($option)) {
                throw new InvalidArgumentException("Invalid answer for calculator question [{$field['name']}].");
            }

            $answers[$field['name']] = $answer;
            $answerLabels[$field['name']] = $option['label'];

            foreach ($option['scores'] as $method => $score) {
                if (array_key_exists($method, $scores)) {
                    $scores[$method] += $score;
                }
            }
        }

        if ($answers === []) {
            throw new InvalidArgumentException('Calculator schema has no scoreable questions.');
        }

        $highestScore = max($scores);
        $recommendedMethod = array_search($highestScore, $scores, true);

        return new CalculationResult(
            calculatorIdentifier: $form->calculator_identifier ?: $form->slug,
            answers: $answers,
            answerLabels: $answerLabels,
            recommendedMethod: $recommendedMethod,
            result: $recommendations[$recommendedMethod],
            scores: $scores,
        );
    }

    private function recommendations(Form $form): array
    {
        $configured = data_get($form->schema, 'calculator.recommendations', []);
        $recommendations = [];

        foreach (is_array($configured) ? $configured : [] as $key => $label) {
            if (is_string($key) && preg_match('/^[a-z][a-z0-9_]*$/', $key) === 1 && is_string($label) && trim($label) !== '') {
                $recommendations[$key] = trim($label);
            }
        }

        if ($recommendations === []) {
            throw new InvalidArgumentException('Calculator schema has no recommendations.');
        }

        return $recommendations;
    }
}
