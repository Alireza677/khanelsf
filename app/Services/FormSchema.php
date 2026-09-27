<?php

namespace App\Services;

use App\Models\Form;
use App\Services\Calculators\CalculatorScoringSchema;
use App\Support\FormNumber;
use App\Support\FormUpload;
use App\Rules\FormUploadRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class FormSchema
{
    public const ALLOWED_COLUMN_SPANS = [3, 4, 6, 8, 9, 12];

    public const DATE_GLOBAL_MIN = '1971-03-21';

    private const INPUT_TYPES = ['text', 'number', 'email', 'tel', 'date', 'textarea', 'select', 'radio', 'checkbox', 'image_choice', 'radio_card', 'file'];

    private const STRUCTURAL_TYPES = ['page', 'step'];

    public static function supportedTypes(): array
    {
        return [...self::INPUT_TYPES, ...self::STRUCTURAL_TYPES];
    }

    public function __construct(
        private readonly FormSchemaIdentityManager $identity,
        private readonly SettingsService $settings,
    ) {}

    public function fields(Form $form, bool $preserveChoiceOptions = false): array
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

            if (in_array($type, self::STRUCTURAL_TYPES, true)) {
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

            if (is_string($field['description'] ?? null)) {
                $normalizedField['description'] = $field['description'];
            }

            if ($type === 'number') {
                $normalizedField['settings'] = [
                    'thousands_separator' => filter_var(data_get($field, 'settings.thousands_separator', false), FILTER_VALIDATE_BOOLEAN),
                    'allow_decimals' => filter_var(data_get($field, 'settings.allow_decimals', false), FILTER_VALIDATE_BOOLEAN),
                    'decimal_places' => $this->decimalPlaces(data_get($field, 'settings.decimal_places', 2)),
                ];
            }

            if ($type === 'file') {
                $mode = FormUpload::mode(data_get($field, 'settings.file_type'));
                $normalizedField['settings'] = [
                    'file_type' => $mode,
                    'max_size_mb' => FormUpload::maxSizeMb($this->settings),
                    'allowed_extensions' => FormUpload::extensions($mode),
                    'allowed_extensions_label' => FormUpload::extensionLabel($mode),
                ];
            }

            if ($type === 'date') {
                $normalizedField['min_date'] = self::normalizeIsoDate($field['min_date'] ?? null);
                $normalizedField['max_date'] = self::normalizeIsoDate($field['max_date'] ?? null);
                $normalizedField['date_range_enabled'] = array_key_exists('date_range_enabled', $field)
                    ? filter_var($field['date_range_enabled'], FILTER_VALIDATE_BOOLEAN)
                    : $normalizedField['min_date'] !== null || $normalizedField['max_date'] !== null;
            }

            if (in_array($type, ['select', 'radio', 'checkbox', 'image_choice', 'radio_card'], true)) {
                $options = $this->options($field['options'] ?? [], data_get($form->schema, 'calculator.criteria', []));

                if ($options === []) {
                    continue;
                }

                $normalizedField['options'] = $type === 'select' && ! $preserveChoiceOptions
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
            if (in_array($field['type'], self::STRUCTURAL_TYPES, true)) {
                continue;
            }

            if ($field['type'] === 'checkbox') {
                $values = array_column($field['options'], 'value');
                $rules[$field['name']] = array_values(array_filter([
                    $field['required'] ? 'required' : 'nullable',
                    'array',
                    $field['required'] ? 'min:1' : null,
                ]));
                $rules[$field['name'].'.*'] = [
                    'string',
                    'distinct',
                    Rule::in($values),
                ];

                continue;
            }

            if ($field['type'] === 'number') {
                $places = $field['settings']['decimal_places'];
                $pattern = $field['settings']['allow_decimals']
                    ? "/^-?\\d+(?:\\.\\d{1,{$places}})?$/"
                    : '/^-?\d+$/';
                $rules[$field['name']] = [
                    $field['required'] ? 'required' : 'nullable',
                    'string',
                    'max:255',
                    "regex:{$pattern}",
                ];

                continue;
            }

            if ($field['type'] === 'file') {
                $rules[$field['name']] = [
                    $field['required'] ? 'required' : 'nullable',
                    'file',
                    new FormUploadRule(
                        $field['settings']['file_type'],
                        $field['settings']['max_size_mb'],
                    ),
                ];

                continue;
            }

            $fieldRules = [$field['required'] ? 'required' : 'nullable', 'string'];
            $fieldRules[] = $field['type'] === 'textarea' ? 'max:5000' : 'max:255';

            if ($field['type'] === 'email') {
                $fieldRules[] = 'email:rfc';
            }

            if ($field['type'] === 'date') {
                $fieldRules[] = 'date_format:Y-m-d';
                $fieldRules[] = 'after_or_equal:'.self::DATE_GLOBAL_MIN;

                if ($field['date_range_enabled'] && $field['min_date'] !== null) {
                    $fieldRules[] = 'after_or_equal:'.$field['min_date'];
                }

                if ($field['date_range_enabled'] && $field['max_date'] !== null) {
                    $fieldRules[] = 'before_or_equal:'.$field['max_date'];
                }
            }

            if (in_array($field['type'], ['select', 'radio', 'image_choice', 'radio_card'], true)) {
                $values = $field['type'] === 'select'
                    ? array_keys($field['options'])
                    : array_column($field['options'], 'value');
                $fieldRules[] = Rule::in($values);
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }

    public function normalizeSubmissionInput(Form $form, array $input): array
    {
        foreach ($this->fields($form) as $field) {
            if ($field['type'] === 'number' && array_key_exists($field['name'], $input)) {
                $input[$field['name']] = FormNumber::canonicalize($input[$field['name']]);
            }
        }

        return $input;
    }

    public function validationMessages(Form $form): array
    {
        $messages = [];

        foreach ($this->fields($form) as $field) {
            if ($field['type'] !== 'file') {
                continue;
            }

            $messages[$field['name'].'.required'] = "انتخاب فایل برای «{$field['label']}» الزامی است.";
            $messages[$field['name'].'.file'] = "فایل انتخاب‌شده برای «{$field['label']}» معتبر نیست.";
            $messages[$field['name'].'.uploaded'] = "بارگذاری فایل «{$field['label']}» ناموفق بود.";
        }

        return $messages;
    }

    private function decimalPlaces(mixed $value): int
    {
        return is_numeric($value) ? max(1, min((int) $value, 4)) : 2;
    }

    private function label(array $field, string $default): string
    {
        return is_string($field['label'] ?? null) && trim($field['label']) !== ''
            ? trim($field['label'])
            : $default;
    }

    public static function normalizeIsoDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s.*)?$/', $value, $matches) === 1) {
            $value = $matches[1];
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') === $value
            ? $value
            : null;
    }

    private function options(mixed $options, mixed $criteria = []): array
    {
        if (! is_array($options)) {
            return [];
        }

        $criteriaById = [];
        foreach (is_array($criteria) ? $criteria : [] as $criterion) {
            if (is_array($criterion) && is_string($criterion['id'] ?? null)) {
                $criteriaById[strtoupper($criterion['id'])] = $criterion;
            }
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

            foreach (['description', 'icon'] as $presentationKey) {
                if (is_string($option[$presentationKey] ?? null)) {
                    $normalized[$value][$presentationKey] = trim($option[$presentationKey]);
                }
            }

            if (array_key_exists('criterion_weights', $option)) {
                try {
                    $normalized[$value]['criterion_weights'] = app(CalculatorScoringSchema::class)->normalizeMap(
                        $option['criterion_weights'], $criteriaById, 10, 'schema.fields.options.criterion_weights',
                    );
                } catch (ValidationException) {
                    // Rendering must remain safe; the manager rejects/logs invalid config before scoring.
                    $normalized[$value]['criterion_weights'] = [];
                }
            }
        }

        return array_values($normalized);
    }
}
