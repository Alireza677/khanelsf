<?php

namespace App\Services\Calculators;

use Illuminate\Support\Str;

final class CalculatorEligibilityRuleSchema
{
    public const CHOICE_TYPES = ['select', 'radio', 'image_choice', 'radio_card'];

    public const CHECKBOX_TYPE = 'checkbox';

    public const NUMBER_TYPE = 'number';

    public const SUPPORTED_TYPES = [...self::CHOICE_TYPES, self::CHECKBOX_TYPE, self::NUMBER_TYPE];

    private const OPERATORS = [
        'select' => ['equals', 'not_equals'],
        'radio' => ['equals', 'not_equals'],
        'image_choice' => ['equals', 'not_equals'],
        'radio_card' => ['equals', 'not_equals'],
        'checkbox' => ['contains', 'not_contains'],
        'number' => [
            'equals',
            'not_equals',
            'greater_than',
            'greater_than_or_equal',
            'less_than',
            'less_than_or_equal',
        ],
    ];

    /**
     * @return list<array{
     *     rule_id: string,
     *     field_id: string,
     *     operator: string,
     *     option_id: string|null,
     *     number_value: int|float|null,
     *     profiles: list<string>,
     *     effect: 'exclude',
     *     reason: string
     * }>
     */
    public function normalize(array $fields, mixed $recommendations, mixed $rules): array
    {
        $fieldsById = collect($fields)
            ->filter(fn (mixed $field): bool => is_array($field)
                && $this->validId($field['field_id'] ?? null)
                && in_array($field['type'] ?? null, self::SUPPORTED_TYPES, true))
            ->keyBy(fn (array $field): string => strtoupper($field['field_id']));
        $profileKeys = $this->profileKeys($recommendations);
        $normalized = [];
        $usedRuleIds = [];

        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $fieldId = is_string($rule['field_id'] ?? null) ? strtoupper($rule['field_id']) : '';
            $field = $fieldsById->get($fieldId);
            $operator = is_string($rule['operator'] ?? null) ? $rule['operator'] : '';
            $reason = is_string($rule['reason'] ?? null) ? trim($rule['reason']) : '';
            $profiles = array_values(array_unique(array_filter(
                is_array($rule['profiles'] ?? null) ? $rule['profiles'] : [],
                fn (mixed $profile): bool => is_string($profile) && in_array($profile, $profileKeys, true),
            )));

            if (! is_array($field)
                || ! in_array($operator, $this->operatorsFor($field['type']), true)
                || $reason === ''
                || $profiles === []
                || (($rule['effect'] ?? 'exclude') !== 'exclude')) {
                continue;
            }

            $optionId = null;
            $numberValue = null;

            if ($field['type'] === self::NUMBER_TYPE) {
                if (! is_numeric($rule['number_value'] ?? null)) {
                    continue;
                }

                $numberValue = $rule['number_value'] + 0;
            } else {
                $optionId = is_string($rule['option_id'] ?? null) ? strtoupper($rule['option_id']) : '';

                if (! collect($field['options'] ?? [])->contains(
                    fn (mixed $option): bool => is_array($option)
                        && strtoupper((string) ($option['option_id'] ?? '')) === $optionId,
                )) {
                    continue;
                }
            }

            $ruleId = is_string($rule['rule_id'] ?? null) ? strtoupper($rule['rule_id']) : '';

            if (! $this->validId($ruleId) || isset($usedRuleIds[$ruleId])) {
                do {
                    $ruleId = strtoupper((string) Str::ulid());
                } while (isset($usedRuleIds[$ruleId]));
            }

            $usedRuleIds[$ruleId] = true;
            $normalized[] = [
                'rule_id' => $ruleId,
                'field_id' => $fieldId,
                'operator' => $operator,
                'option_id' => $optionId,
                'number_value' => $numberValue,
                'profiles' => $profiles,
                'effect' => 'exclude',
                'reason' => $reason,
            ];
        }

        return $normalized;
    }

    /** @return list<string> */
    public function operatorsFor(?string $type): array
    {
        return self::OPERATORS[$type] ?? [];
    }

    public function isChoice(?string $type): bool
    {
        return in_array($type, [...self::CHOICE_TYPES, self::CHECKBOX_TYPE], true);
    }

    /** @return list<string> */
    private function profileKeys(mixed $recommendations): array
    {
        $keys = [];

        foreach (is_array($recommendations) ? $recommendations : [] as $key => $recommendation) {
            $candidate = is_array($recommendation) ? ($recommendation['key'] ?? null) : $key;

            if (is_string($candidate) && preg_match('/^[a-z][a-z0-9_]*$/', $candidate) === 1) {
                $keys[] = $candidate;
            }
        }

        return array_values(array_unique($keys));
    }

    private function validId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', strtoupper($id)) === 1;
    }
}
