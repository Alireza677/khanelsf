<?php

namespace App\Services;

use App\Models\Form;
use Illuminate\Validation\Rule;

final class FormSchema
{
    public const ALLOWED_COLUMN_SPANS = [3, 4, 6, 8, 9, 12];

    private const INPUT_TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'image_choice', 'radio_card'];

    private const PAGE_TYPES = ['page', 'step'];

    public function __construct(private readonly FormSchemaIdentityManager $identity) {}

    public function fields(Form $form): array
    {
        $fields = is_array($form->schema) ? ($form->schema['fields'] ?? []) : [];

        if (! is_array($fields)) {
            $fields = [];
        }

        $fields = $this->identity->canonicalize($fields);

        $normalized = [];
        $usedNames = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? null;
            $type = $field['type'] ?? 'text';

            if (in_array($type, self::PAGE_TYPES, true)) {
                $normalized[] = [
                    'field_id' => $field['field_id'],
                    'key' => $key,
                    'name' => is_string($key) && preg_match('/^[a-z][a-z0-9_]*$/', $key) === 1
                        ? $key
                        : 'step_'.(count($normalized) + 1),
                    'label' => $this->label($field, 'مرحله جدید'),
                    'type' => $type,
                    'description' => is_string($field['description'] ?? null) ? $field['description'] : null,
                    'layout' => ['span' => 12],
                ];

                continue;
            }

            if (! is_string($key)
                || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1
                || isset($usedNames[$key])
                || ! in_array($type, self::INPUT_TYPES, true)) {
                continue;
            }

            $normalizedField = [
                'field_id' => $field['field_id'],
                'key' => $key,
                'name' => $key,
                'label' => $this->label($field, str($key)->headline()->toString()),
                'type' => $type,
                'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'placeholder' => is_string($field['placeholder'] ?? null) ? $field['placeholder'] : null,
                'layout' => ['span' => self::normalizeColumnSpan(data_get($field, 'layout.span'))],
            ];

            if (in_array($type, ['select', 'image_choice', 'radio_card'], true)) {
                $options = $this->options($field['options'] ?? []);

                if ($options === []) {
                    continue;
                }

                $normalizedField['options'] = $type === 'select'
                    ? collect($options)->pluck('label', 'value')->all()
                    : $options;
            }

            $normalized[] = $normalizedField;
            $usedNames[$key] = true;
        }

        return $normalized;
    }

    public static function normalizeColumnSpan(mixed $span): int
    {
        $span = is_numeric($span) ? (int) $span : 12;

        return in_array($span, self::ALLOWED_COLUMN_SPANS, true) ? $span : 12;
    }

    public function validationRules(Form $form): array
    {
        $rules = [];

        foreach ($this->fields($form) as $field) {
            if (in_array($field['type'], self::PAGE_TYPES, true)) {
                continue;
            }

            $fieldRules = [$field['required'] ? 'required' : 'nullable', 'string'];
            $fieldRules[] = $field['type'] === 'textarea' ? 'max:5000' : 'max:255';

            if ($field['type'] === 'email') {
                $fieldRules[] = 'email:rfc';
            }

            if (in_array($field['type'], ['select', 'image_choice', 'radio_card'], true)) {
                $values = $field['type'] === 'select'
                    ? array_keys($field['options'])
                    : array_column($field['options'], 'value');
                $fieldRules[] = Rule::in($values);
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }

    private function label(array $field, string $default): string
    {
        return is_string($field['label'] ?? null) && trim($field['label']) !== ''
            ? trim($field['label'])
            : $default;
    }

    private function options(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $option) {
            if (is_string($option)) {
                $option = ['value' => $key, 'label' => $option];
            }

            if (! is_array($option)) {
                continue;
            }

            $value = $option['value'] ?? (is_string($key) ? $key : null);
            $label = $option['label'] ?? null;

            if (! is_string($value) || $value === '' || ! is_string($label) || trim($label) === '' || isset($normalized[$value])) {
                continue;
            }

            $scores = [];
            foreach (is_array($option['scores'] ?? null) ? $option['scores'] : [] as $method => $score) {
                if (is_string($method) && preg_match('/^[a-z][a-z0-9_]*$/', $method) === 1 && is_numeric($score)) {
                    $scores[$method] = $score + 0;
                }
            }

            $normalized[$value] = [
                'option_id' => $option['option_id'],
                'value' => $value,
                'label' => trim($label),
                'image' => is_string($option['image'] ?? null) && trim($option['image']) !== '' ? trim($option['image']) : null,
                'scores' => $scores,
            ];
        }

        return array_values($normalized);
    }
}
