<?php

namespace App\Services\Calculators;

use App\Models\Form;
use App\Services\FormSchema;
use InvalidArgumentException;

final class CalculatorManager
{
    private const SINGLE_CHOICE_TYPES = ['image_choice', 'radio_card', 'radio'];

    public function __construct(
        private readonly FormSchema $schema,
        private readonly CalculatorEligibilityEvaluator $eligibilityEvaluator,
    ) {}

    public function calculate(Form $form, array $payload): CalculationResult
    {
        if (! $form->isCalculator()) {
            throw new InvalidArgumentException('Only calculator forms can be scored.');
        }

        $recommendations = $this->recommendations($form);
        $scores = array_fill_keys(array_keys($recommendations), 0);
        $answers = [];
        $answerLabels = [];
        $scoreableFieldCount = 0;

        foreach ($this->schema->fields($form) as $field) {
            if (in_array($field['type'], self::SINGLE_CHOICE_TYPES, true)) {
                $scoreableFieldCount++;
                $this->scoreSingleChoice($field, $payload, $scores, $answers, $answerLabels);

                continue;
            }

            if ($field['type'] === 'checkbox') {
                $scoreableFieldCount++;
                $this->scoreCheckbox($field, $payload, $scores, $answers, $answerLabels);
            }
        }

        if ($scoreableFieldCount === 0) {
            throw new InvalidArgumentException('Calculator schema has no scoreable questions.');
        }

        $eligibility = $this->eligibilityEvaluator->evaluate($form, $payload, $recommendations);
        $recommendedMethod = $this->recommendedMethod($recommendations, $scores, $eligibility);
        $ranking = $this->ranking($recommendations, $scores, $eligibility);

        return new CalculationResult(
            calculatorIdentifier: $form->calculator_identifier ?: $form->slug,
            answers: $answers,
            answerLabels: $answerLabels,
            recommendedMethod: $recommendedMethod,
            result: $recommendedMethod === null ? null : $recommendations[$recommendedMethod],
            scores: $scores,
            ranking: $ranking,
            eligibility: $eligibility,
            noEligibleRecommendation: $recommendedMethod === null,
        );
    }

    private function scoreSingleChoice(
        array $field,
        array $payload,
        array &$scores,
        array &$answers,
        array &$answerLabels,
    ): void {
        $answer = $payload[$field['name']] ?? null;

        if (($answer === null || $answer === '') && ! $field['required']) {
            return;
        }

        if (! is_string($answer) || ! is_array($option = $this->option($field, $answer))) {
            throw new InvalidArgumentException("Invalid answer for calculator question [{$field['name']}].");
        }

        $answers[$field['name']] = $answer;
        $answerLabels[$field['name']] = $option['label'];
        $this->applyOptionScores($option, $scores);
    }

    private function scoreCheckbox(
        array $field,
        array $payload,
        array &$scores,
        array &$answers,
        array &$answerLabels,
    ): void {
        $answer = $payload[$field['name']] ?? null;

        if (($answer === null || $answer === []) && ! $field['required']) {
            return;
        }

        if (! is_array($answer)
            || $answer === []
            || count($answer) !== count(array_filter($answer, 'is_string'))
            || count($answer) !== count(array_unique($answer, SORT_STRING))) {
            throw new InvalidArgumentException("Invalid answer for calculator question [{$field['name']}].");
        }

        $labels = [];

        foreach ($answer as $value) {
            $option = $this->option($field, $value);

            if (! is_array($option)) {
                throw new InvalidArgumentException("Invalid answer for calculator question [{$field['name']}].");
            }

            $labels[] = $option['label'];
            $this->applyOptionScores($option, $scores);
        }

        $answers[$field['name']] = array_values($answer);
        $answerLabels[$field['name']] = implode('، ', $labels);
    }

    private function option(array $field, string $value): ?array
    {
        foreach ($field['options'] as $option) {
            if (is_array($option) && $option['value'] === $value) {
                return $option;
            }
        }

        return null;
    }

    private function applyOptionScores(array $option, array &$scores): void
    {
        foreach ($option['scores'] as $method => $score) {
            if (array_key_exists($method, $scores)) {
                $scores[$method] += $score;
            }
        }
    }

    private function recommendedMethod(array $recommendations, array $scores, array $eligibility): ?string
    {
        $eligibleScores = array_filter(
            $scores,
            fn (string $key): bool => ($eligibility[$key]['eligible'] ?? false) === true,
            ARRAY_FILTER_USE_KEY,
        );

        if ($eligibleScores === []) {
            return null;
        }

        $highestScore = max($eligibleScores);

        foreach ($recommendations as $key => $_label) {
            if (($eligibility[$key]['eligible'] ?? false) === true && $scores[$key] === $highestScore) {
                return $key;
            }
        }

        return null;
    }

    private function ranking(array $recommendations, array $scores, array $eligibility): array
    {
        $ranking = [];

        foreach ($recommendations as $key => $label) {
            $ranking[] = [
                'key' => $key,
                'label' => $label,
                'score' => $scores[$key],
                'eligible' => $eligibility[$key]['eligible'],
                'reasons' => $eligibility[$key]['reasons'],
                'order' => count($ranking),
            ];
        }

        usort($ranking, fn (array $left, array $right): int => ($right['eligible'] <=> $left['eligible'])
            ?: ($right['score'] <=> $left['score'])
            ?: ($left['order'] <=> $right['order']));

        $rank = 0;

        return array_map(static function (array $row) use (&$rank): array {
            return [
                'key' => $row['key'],
                'label' => $row['label'],
                'score' => $row['score'],
                'rank' => $row['eligible'] ? ++$rank : null,
                'eligible' => $row['eligible'],
                'reasons' => $row['reasons'],
            ];
        }, $ranking);
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
