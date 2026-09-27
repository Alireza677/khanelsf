<?php

namespace App\Filament\Support;

use Illuminate\Validation\ValidationException;

/** Presentation adapters; persistence remains the Phase 1 schema contract. */
final class CalculatorWeightedEditor
{
    public static function criteriaOptions(mixed $criteria): array
    {
        $options = [];
        foreach (is_array($criteria) ? $criteria : [] as $criterion) {
            $id = is_array($criterion) ? ($criterion['id'] ?? null) : null;
            if (is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/iD', $id) === 1) {
                $options[strtoupper($id)] = filled($criterion['label'] ?? null) ? $criterion['label'] : 'معیار بدون عنوان';
            }
        }

        return $options;
    }

    public static function resultOptions(mixed $results): array
    {
        $options = [];
        foreach (is_array($results) ? $results : [] as $key => $result) {
            $label = is_array($result) ? ($result['label'] ?? null) : $result;
            $key = is_array($result) ? ($result['key'] ?? null) : $key;
            if (is_string($key) && preg_match('/^[a-z][a-z0-9_]*$/D', $key) === 1) {
                $options[$key] = is_string($label) && trim($label) !== '' ? $label : 'نتیجه بدون عنوان';
            }
        }

        return $options;
    }

    public static function effectsForEditor(mixed $weights): array
    {
        $rows = [];
        foreach (is_array($weights) ? $weights : [] as $id => $weight) {
            $rows[] = ['criterion_id' => $id, 'weight' => $weight];
        }

        return $rows;
    }

    public static function effectsForStorage(mixed $weights, string $path): mixed
    {
        if (! is_array($weights)) {
            return $weights;
        }
        $map = [];
        foreach ($weights as $id => $weight) {
            if (is_array($weight)) {
                $id = $weight['criterion_id'] ?? null;
                $weight = $weight['weight'] ?? 0;
            }
            if (! is_string($id) || preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/iD', $id) !== 1) {
                throw ValidationException::withMessages([$path => 'انتخاب معیار معتبر الزامی است.']);
            }
            $id = strtoupper($id);
            if (array_key_exists($id, $map)) {
                throw ValidationException::withMessages([$path => 'هر معیار را برای یک پاسخ فقط یک‌بار انتخاب کنید.']);
            }
            $map[$id] = $weight === '' ? 0 : $weight;
        }

        return $map;
    }

    /** Remove stale references without validating partially edited labels/numbers. */
    public static function pruneReferences(array $schema, bool $editorRows = false): array
    {
        $criteria = self::criteriaOptions(data_get($schema, 'calculator.criteria', []));
        $results = self::resultOptions(data_get($schema, 'calculator.recommendations', []));
        $filter = static function (mixed $map) use ($criteria): mixed {
            if (! is_array($map)) {
                return $map;
            }

            return array_filter($map, fn ($id): bool => is_string($id) && isset($criteria[strtoupper($id)]), ARRAY_FILTER_USE_KEY);
        };
        if (is_array(data_get($schema, 'calculator.criterion_scores'))) {
            $matrix = [];
            foreach ($schema['calculator']['criterion_scores'] as $key => $scores) {
                if (isset($results[$key])) {
                    $matrix[$key] = $filter($scores);
                }
            }
            $schema['calculator']['criterion_scores'] = $matrix;
        }
        foreach ($schema['fields'] ?? [] as $fieldKey => $field) {
            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $optionKey => $option) {
                if (! is_array($option) || ! array_key_exists('criterion_weights', $option)) {
                    continue;
                }
                $weights = $option['criterion_weights'];
                if ($editorRows && is_array($weights)) {
                    $weights = array_filter($weights, static function ($row) use ($criteria): bool {
                        $id = is_array($row) ? ($row['criterion_id'] ?? null) : null;

                        // Keep unfinished rows so normal field validation can explain them.
                        return blank($id) || (is_string($id) && isset($criteria[strtoupper($id)]));
                    });
                } else {
                    $weights = $filter($weights);
                }
                $schema['fields'][$fieldKey]['options'][$optionKey]['criterion_weights'] = $weights;
            }
        }

        return $schema;
    }
}
