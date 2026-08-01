<?php

namespace App\Services;

use App\Models\Form;

final class SubmissionAnswerSnapshot
{
    public const PAYLOAD_KEY = '_answer_snapshot';

    public function __construct(private readonly FormSchema $schema) {}

    /**
     * @return list<array{field_key: string, field_label: string, raw_value: mixed, display_value: mixed}>
     */
    public function answers(Form $form, array $payload): array
    {
        $answers = [];

        foreach ($this->schema->fields($form) as $field) {
            $key = $field['name'];

            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $answers[] = [
                'field_key' => $key,
                'field_label' => $field['label'],
                'raw_value' => $payload[$key],
                'display_value' => $this->displayValue($field, $payload[$key]),
            ];
        }

        return $answers;
    }

    public function enrichCalculationResult(Form $form, array $result, array $answers): array
    {
        $labels = $this->recommendationLabels($form);
        $fieldLabels = [];

        foreach ($answers as $answer) {
            $fieldLabels[$answer['field_key']] = $answer['field_label'];
        }

        return [
            ...$result,
            'answer_field_labels' => $fieldLabels,
            'recommendation_labels' => $labels,
            'score_labels' => $labels,
        ];
    }

    /** @return array<string, string> */
    private function recommendationLabels(Form $form): array
    {
        $configured = data_get($form->schema, 'calculator.recommendations', []);
        $labels = [];

        foreach (is_array($configured) ? $configured : [] as $key => $recommendation) {
            if (is_string($recommendation) && is_string($key)) {
                $labels[$key] = trim($recommendation);

                continue;
            }

            if (! is_array($recommendation)) {
                continue;
            }

            $recommendationKey = $recommendation['key'] ?? null;
            $label = $recommendation['label'] ?? null;

            if (is_string($recommendationKey) && is_string($label) && trim($label) !== '') {
                $labels[$recommendationKey] = trim($label);
            }
        }

        return array_filter($labels, fn (string $label): bool => $label !== '');
    }

    private function displayValue(array $field, mixed $value): mixed
    {
        if (($field['type'] ?? null) === 'select') {
            $options = is_array($field['options'] ?? null) ? $field['options'] : [];

            return is_scalar($value) && array_key_exists((string) $value, $options)
                ? $options[(string) $value]
                : $value;
        }

        if (in_array($field['type'] ?? null, ['image_choice', 'radio_card'], true)) {
            $option = collect($field['options'] ?? [])->firstWhere('value', $value);

            return is_array($option) ? ($option['label'] ?? $value) : $value;
        }

        return $value;
    }
}
