<?php

namespace App\Services\Calculators;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** The additive scoring contract stored exclusively in forms.schema. */
final class CalculatorScoringSchema
{
    public const SIMPLE = 'simple';

    public const WEIGHTED = 'weighted';

    public function scoringMode(array $schema, string $path = 'schema'): string
    {
        $calculator = $schema['calculator'] ?? [];
        if (! is_array($calculator)) {
            $this->invalid("{$path}.calculator", 'تنظیمات محاسبه‌گر معتبر نیست.');
        }

        $mode = array_key_exists('scoring_mode', $calculator) ? $calculator['scoring_mode'] : self::SIMPLE;
        if (! in_array($mode, [self::SIMPLE, self::WEIGHTED], true)) {
            $this->invalid("{$path}.calculator.scoring_mode", 'روش امتیازدهی معتبر نیست.');
        }

        return $mode;
    }

    public function newCriterion(string $label, ?string $description = null, int|float $baseWeight = 0): array
    {
        return $this->normalizeCriterion([
            'id' => (string) Str::ulid(),
            'label' => $label,
            'description' => $description,
            'base_weight' => $baseWeight,
        ], 'schema.calculator.criteria');
    }

    /** Validate before coercion/pruning so malformed values are never silently repaired. */
    public function normalize(array $schema, string $path = 'schema'): array
    {
        $mode = $this->scoringMode($schema, $path);
        $calculator = $schema['calculator'] ?? [];
        $criteria = [];

        if (array_key_exists('criteria', $calculator) || $mode === self::WEIGHTED) {
            $rawCriteria = array_key_exists('criteria', $calculator) ? $calculator['criteria'] : [];
            if (! is_array($rawCriteria) || ! array_is_list($rawCriteria)) {
                $this->invalid("{$path}.calculator.criteria", 'فهرست معیارها معتبر نیست.');
            }

            foreach ($rawCriteria as $index => $criterion) {
                $criterionPath = "{$path}.calculator.criteria.{$index}";
                if (! is_array($criterion)) {
                    $this->invalid($criterionPath, 'اطلاعات معیار معتبر نیست.');
                }
                $criterion = $this->normalizeCriterion($criterion, $criterionPath);
                if (isset($criteria[$criterion['id']])) {
                    $this->invalid("{$criterionPath}.id", 'شناسهٔ معیار تکراری است.');
                }
                $criteria[$criterion['id']] = $criterion;
            }

            $schema['calculator']['criteria'] = array_values($criteria);
        }

        foreach ($schema['fields'] ?? [] as $fieldIndex => $field) {
            if (! is_array($field) || ! is_array($field['options'] ?? null)) {
                continue;
            }
            foreach ($field['options'] as $optionIndex => $option) {
                if (is_array($option) && array_key_exists('criterion_weights', $option)) {
                    $schema['fields'][$fieldIndex]['options'][$optionIndex]['criterion_weights'] = $this->normalizeMap(
                        $option['criterion_weights'],
                        $criteria,
                        10,
                        "{$path}.fields.{$fieldIndex}.options.{$optionIndex}.criterion_weights",
                    );
                }
            }
        }

        if ($mode === self::WEIGHTED || array_key_exists('criterion_scores', $calculator)) {
            $schema['calculator']['criterion_scores'] = $this->normalizePerformanceMatrix(
                $calculator['criterion_scores'] ?? [],
                array_column($criteria, 'label', 'id'),
                $calculator['recommendations'] ?? [],
                "{$path}.calculator.criterion_scores",
            );
        }

        // Opt-in metadata: legacy schemas stay absent, and simple mode retains it untouched.
        if ($mode === self::WEIGHTED && array_key_exists('decision_report', $calculator)) {
            $schema['calculator']['decision_report'] = $this->normalizeDecisionReport(
                $calculator['decision_report'],
                $criteria,
                $calculator['recommendations'] ?? [],
                "{$path}.calculator.decision_report",
            );
        }

        if (array_key_exists('result_content', $calculator)) {
            $schema['calculator']['result_content'] = app(CalculatorResultContent::class)->normalize(
                $calculator['result_content'],
                $calculator['recommendations'] ?? [],
                "{$path}.calculator.result_content",
            );
        }

        return $schema;
    }

    /** Normalize optional report metadata; this does not affect scoring or top factors. */
    private function normalizeDecisionReport(mixed $report, array $criteria, array $results, string $path): array
    {
        if (! is_array($report)) {
            $this->invalid($path, 'تنظیمات گزارش تصمیم معتبر نیست.');
        }

        $enabled = array_key_exists('enabled', $report) ? $report['enabled'] : false;
        if (! is_bool($enabled)) {
            $this->invalid("{$path}.enabled", 'فعال‌بودن گزارش تصمیم باید مقدار منطقی باشد.');
        }
        $count = array_key_exists('top_factors_count', $report) ? $report['top_factors_count'] : 3;
        if (! is_int($count) || $count < 1) {
            $this->invalid("{$path}.top_factors_count", 'تعداد عوامل مؤثر باید عدد صحیح مثبت باشد.');
        }

        $explanations = $report['explanations'] ?? [];
        if (! is_array($explanations)) {
            $this->invalid("{$path}.explanations", 'ساختار توضیحات گزارش تصمیم معتبر نیست.');
        }
        $normalized = [];
        foreach ($explanations as $resultKey => $texts) {
            $resultPath = "{$path}.explanations.{$resultKey}";
            if (! is_string($resultKey) || preg_match('/^[a-z][a-z0-9_]*$/D', $resultKey) !== 1) {
                $this->invalid("{$path}.explanations", 'شناسهٔ نتیجه معتبر نیست.');
            }
            if (! is_array($texts)) {
                $this->invalid($resultPath, 'توضیحات معیارهای نتیجه معتبر نیست.');
            }
            $seen = [];
            foreach ($texts as $id => $text) {
                $id = $this->criterionId($id, $resultPath);
                if (isset($seen[$id])) {
                    $this->invalid($resultPath, 'شناسهٔ معیار تکراری است.');
                }
                $seen[$id] = true;
                if ($text !== null && ! is_string($text)) {
                    $this->invalid("{$resultPath}.{$id}", 'توضیح معیار باید متن یا خالی باشد.');
                }
                $text = trim($text ?? '');
                // Keep a sparse map, pruning deleted references and optional empty text.
                if ($text !== '' && array_key_exists($resultKey, $results) && array_key_exists($id, $criteria)) {
                    $normalized[$resultKey][$id] = $text;
                }
            }
        }

        return [
            'enabled' => $enabled,
            'top_factors_count' => $count,
            'explanations' => $normalized,
        ];
    }

    /**
     * Complete the result-key × criterion-ID matrix without changing live editor state.
     * Valid deleted references are still validated, then pruned as in the existing contract.
     *
     * @param array<string, string> $criteria Criterion IDs mapped to labels.
     * @param array<string, string> $results Result keys mapped to labels.
     */
    public function normalizePerformanceMatrix(mixed $values, array $criteria, array $results, string $path): array
    {
        if ($values === null || $values === '') {
            $values = [];
        }
        if (! is_array($values)) {
            $this->invalid($path, 'جدول امتیاز معیارهای نتایج معتبر نیست.');
        }

        $matrix = array_fill_keys(array_keys($results), array_fill_keys(array_keys($criteria), 0));
        foreach ($values as $resultKey => $scores) {
            if (! is_string($resultKey) || preg_match('/^[a-z][a-z0-9_]*$/D', $resultKey) !== 1) {
                $this->invalid($path, 'شناسهٔ نتیجه معتبر نیست.');
            }
            if ($scores === null || $scores === '') {
                $scores = [];
            }
            if (! is_array($scores)) {
                $this->invalid("{$path}.{$resultKey}", 'مقادیر امتیازدهی معیارها معتبر نیست.');
            }
            $seen = [];
            foreach ($scores as $id => $value) {
                $id = $this->criterionId($id, "{$path}.{$resultKey}");
                if (isset($seen[$id])) {
                    $this->invalid("{$path}.{$resultKey}", 'شناسهٔ معیار تکراری است.');
                }
                $seen[$id] = true;
                $value = $this->number(
                    $value === null || $value === '' ? 0 : $value,
                    5,
                    "{$path}.{$resultKey}.{$id}",
                    isset($criteria[$id], $results[$resultKey])
                        ? self::performanceMessage($criteria[$id], $results[$resultKey])
                        : null,
                );
                if (isset($criteria[$id], $results[$resultKey])) {
                    $matrix[$resultKey][$id] = $value;
                }
            }
        }

        return $matrix;
    }

    public static function performanceMessage(string $criterion, string $result): string
    {
        return "امتیاز «{$criterion}» برای «{$result}» باید عددی بین ۰ تا ۵ باشد.";
    }

    private function normalizeCriterion(array $criterion, string $path): array
    {
        // Only an absent ID denotes a new criterion. Invalid or duplicate IDs must fail.
        $id = array_key_exists('id', $criterion) ? $this->criterionId($criterion['id'], "{$path}.id") : (string) Str::ulid();
        $label = $criterion['label'] ?? null;
        if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 255) {
            $this->invalid("{$path}.label", 'عنوان معیار الزامی است و باید حداکثر ۲۵۵ نویسه باشد.');
        }
        $description = $criterion['description'] ?? null;
        if ($description !== null && (! is_string($description) || mb_strlen($description) > 2000)) {
            $this->invalid("{$path}.description", 'توضیح معیار باید متن و حداکثر ۲۰۰۰ نویسه باشد.');
        }

        $normalized = [
            'id' => $id,
            'label' => trim($label),
            'base_weight' => $this->number(array_key_exists('base_weight', $criterion) ? $criterion['base_weight'] : 0, 10, "{$path}.base_weight"),
        ];
        if (array_key_exists('description', $criterion)) {
            $normalized['description'] = $description === null ? null : trim($description);
        }

        return $normalized;
    }

    /** Sparse maps: missing entries mean zero; valid references to deleted criteria are removed. */
    public function normalizeMap(mixed $values, array $criteriaById, int $maximum, string $path): array
    {
        if (! is_array($values)) {
            $this->invalid($path, 'مقادیر امتیازدهی معیارها معتبر نیست.');
        }
        $normalized = [];
        $seen = [];
        foreach ($values as $id => $value) {
            $id = $this->criterionId($id, $path);
            if (isset($seen[$id])) {
                $this->invalid($path, 'شناسهٔ معیار تکراری است.');
            }
            $seen[$id] = true;
            $value = $this->number($value, $maximum, $path);
            if (array_key_exists($id, $criteriaById)) {
                $normalized[$id] = $value;
            }
        }

        return $normalized;
    }

    private function criterionId(mixed $id, string $path): string
    {
        if (! is_string($id) || preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/iD', $id) !== 1) {
            $this->invalid($path, 'شناسهٔ معیار معتبر نیست.');
        }

        return strtoupper($id);
    }

    private function number(mixed $value, int $maximum, string $path, ?string $message = null): int|float
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value))
            || ! is_numeric($value) || ! is_finite((float) $value) || $value < 0 || $value > $maximum) {
            $this->invalid($path, $message ?? ($maximum === 10
                ? 'وزن معیار باید عددی بین ۰ تا ۱۰ باشد.'
                : 'امتیاز عملکرد معیار باید عددی بین ۰ تا ۵ باشد.'));
        }

        return $value + 0;
    }

    private function invalid(string $path, string $message): never
    {
        throw ValidationException::withMessages([$path => $message]);
    }
}
