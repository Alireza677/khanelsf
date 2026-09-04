<?php

namespace App\Services\Calculators;

use App\Models\Form;
use App\Services\FormSchemaIdentityManager;
use App\Support\FormNumber;

final class CalculatorEligibilityEvaluator
{
    public function __construct(
        private readonly FormSchemaIdentityManager $identity,
        private readonly CalculatorEligibilityRuleSchema $ruleSchema,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, string>  $recommendations
     * @return array<string, array{eligible: bool, reasons: list<array{rule_id: string, message: string}>}>
     */
    public function evaluate(Form $form, array $answers, array $recommendations): array
    {
        $eligibility = [];

        foreach ($recommendations as $key => $_label) {
            $eligibility[$key] = ['eligible' => true, 'reasons' => []];
        }

        $schema = is_array($form->schema) ? $form->schema : [];
        $fields = $this->identity->canonicalize(is_array($schema['fields'] ?? null) ? $schema['fields'] : []);
        $rules = $this->ruleSchema->normalize(
            $fields,
            $recommendations,
            data_get($schema, 'calculator.eligibility_rules', []),
        );
        $fieldsById = collect($fields)->keyBy(fn (array $field): string => strtoupper($field['field_id']));

        foreach ($rules as $rule) {
            $field = $fieldsById->get($rule['field_id']);

            if (! is_array($field) || ! $this->matches($field, $rule, $answers)) {
                continue;
            }

            foreach ($rule['profiles'] as $profile) {
                if (! isset($eligibility[$profile])) {
                    continue;
                }

                $eligibility[$profile]['eligible'] = false;
                $reason = ['rule_id' => $rule['rule_id'], 'message' => $rule['reason']];

                if (! in_array($reason, $eligibility[$profile]['reasons'], true)) {
                    $eligibility[$profile]['reasons'][] = $reason;
                }
            }
        }

        return $eligibility;
    }

    private function matches(array $field, array $rule, array $answers): bool
    {
        $key = $field['key'] ?? $field['name'] ?? null;

        if (! is_string($key) || ! array_key_exists($key, $answers) || $this->unanswered($answers[$key])) {
            return false;
        }

        $answer = $answers[$key];
        $operator = $rule['operator'];

        if (($field['type'] ?? null) === CalculatorEligibilityRuleSchema::NUMBER_TYPE) {
            $answer = FormNumber::canonicalize($answer);

            if (! is_numeric($answer)) {
                return false;
            }

            return $this->compareNumbers($answer + 0, $rule['number_value'], $operator);
        }

        $option = collect($field['options'] ?? [])->first(
            fn (mixed $option): bool => is_array($option)
                && strtoupper((string) ($option['option_id'] ?? '')) === $rule['option_id'],
        );
        $expected = is_array($option) ? ($option['value'] ?? null) : null;

        if (! is_string($expected)) {
            return false;
        }

        if (($field['type'] ?? null) === CalculatorEligibilityRuleSchema::CHECKBOX_TYPE) {
            if (! is_array($answer)) {
                return false;
            }

            return match ($operator) {
                'contains' => in_array($expected, $answer, true),
                'not_contains' => ! in_array($expected, $answer, true),
                default => false,
            };
        }

        if (! is_string($answer)) {
            return false;
        }

        return match ($operator) {
            'equals' => $answer === $expected,
            'not_equals' => $answer !== $expected,
            default => false,
        };
    }

    private function compareNumbers(int|float $answer, int|float $expected, string $operator): bool
    {
        return match ($operator) {
            'equals' => $answer == $expected,
            'not_equals' => $answer != $expected,
            'greater_than' => $answer > $expected,
            'greater_than_or_equal' => $answer >= $expected,
            'less_than' => $answer < $expected,
            'less_than_or_equal' => $answer <= $expected,
            default => false,
        };
    }

    private function unanswered(mixed $answer): bool
    {
        return $answer === null || $answer === '' || $answer === [];
    }
}
