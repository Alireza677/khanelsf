<?php

namespace App\Services\Calculators;

use App\Support\PersianDate;

/** Adds deterministic explanations to the resolved result; never selects or scores factors. */
final class DecisionReportBuilder
{
    public const FALLBACK_EXPLANATION = 'این معیار یکی از عوامل مؤثر در پیشنهاد این گزینه بوده است.';

    public function build(
        string $scoringMode,
        array $config,
        ?string $resultKey,
        ?string $resultLabel,
        ?string $suitabilityPercentage,
        array $topFactors,
        bool $noScore,
        bool $noEligibleRecommendation,
    ): ?array {
        if ($scoringMode !== CalculatorScoringSchema::WEIGHTED
            || ($config['enabled'] ?? false) !== true
            || $resultKey === null || $resultLabel === null
            || $noScore || $noEligibleRecommendation) {
            return null;
        }

        $factors = [];
        foreach ($topFactors as $factor) {
            $explanation = $config['explanations'][$resultKey][$factor['criterion_id']] ?? null;
            $factors[] = [
                'criterion_id' => $factor['criterion_id'],
                'label' => $factor['label'],
                'contribution' => $factor['contribution'],
                'explanation' => is_string($explanation) && trim($explanation) !== ''
                    ? $explanation
                    : self::FALLBACK_EXPLANATION,
            ];
        }

        $intro = "بر اساس پاسخ‌های شما، عوامل زیر بیشترین تأثیر را در پیشنهاد «{$resultLabel}» داشته‌اند.";
        $percentageLabel = PersianDate::digits(str_replace('.', '٫', $suitabilityPercentage ?? ''));
        $summary = "مجموع این عوامل باعث شده «{$resultLabel}» با میزان تطابق {$percentageLabel}٪ به‌عنوان پیشنهاد اصلی نمایش داده شود.";

        return [
            'version' => 1,
            'result_key' => $resultKey,
            'result_label' => $resultLabel,
            'suitability_percentage' => $suitabilityPercentage,
            'intro' => $intro,
            'factors' => $factors,
            'summary' => $summary,
            'rendered_text' => implode("\n\n", [
                $intro,
                ...array_map(fn (array $factor): string => $factor['label']."\n".$factor['explanation'], $factors),
                $summary,
            ]),
        ];
    }
}
