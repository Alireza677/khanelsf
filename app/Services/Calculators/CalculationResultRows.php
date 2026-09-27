<?php

namespace App\Services\Calculators;

use App\Support\PersianDate;

final class CalculationResultRows
{
    /**
     * @return list<array{key: string, label: string, score: mixed, rank: int|null, recommended: bool, eligible: bool|null, reasons: list<array{rule_id: string, message: string}>}>
     */
    public function fromSnapshot(array $result, string $fallbackLabel = 'نتیجه'): array
    {
        $ranking = $this->storedRanking($result);

        if ($ranking !== []) {
            return $this->weightedRows($ranking, $result);
        }

        $scores = is_array($result['scores'] ?? null) ? $result['scores'] : [];
        $labels = is_array($result['score_labels'] ?? null) ? $result['score_labels'] : [];
        $eligibility = is_array($result['eligibility'] ?? null) ? $result['eligibility'] : [];
        $recommendedKey = is_string($result['recommended_method'] ?? null)
            ? $result['recommended_method']
            : null;
        $recommendation = is_string($result['result'] ?? null) ? trim($result['result']) : '';
        $rows = [];

        foreach ($scores as $key => $score) {
            if (! is_scalar($score)) {
                continue;
            }

            $key = (string) $key;
            $isRecommended = $key === $recommendedKey;
            $label = is_scalar($labels[$key] ?? null) ? trim((string) $labels[$key]) : '';
            $profileEligibility = is_array($eligibility[$key] ?? null) ? $eligibility[$key] : [];
            $eligible = is_bool($profileEligibility['eligible'] ?? null) ? $profileEligibility['eligible'] : null;

            $rows[] = [
                'key' => $key,
                'label' => $label !== ''
                    ? $label
                    : ($isRecommended && $recommendation !== '' ? $recommendation : $fallbackLabel.' '.(count($rows) + 1)),
                'score' => $score,
                'rank' => null,
                'recommended' => $isRecommended,
                'eligible' => $eligible,
                'reasons' => $this->reasons($profileEligibility['reasons'] ?? []),
            ];
        }

        return $this->weightedRows($rows, $result);
    }

    /**
     * @return list<array{key: string, label: string, score: int|float, rank: int|null, recommended: bool, eligible: bool|null, reasons: list<array{rule_id: string, message: string}>}>
     */
    private function storedRanking(array $result): array
    {
        $ranking = $result['ranking'] ?? null;

        if (! is_array($ranking) || $ranking === []) {
            return [];
        }

        $recommendedKey = is_string($result['recommended_method'] ?? null)
            ? $result['recommended_method']
            : null;
        $eligibility = is_array($result['eligibility'] ?? null) ? $result['eligibility'] : [];
        $rows = [];
        $seenKeys = [];

        foreach ($ranking as $row) {
            $key = is_array($row) && is_string($row['key'] ?? null) ? $row['key'] : '';
            $label = is_array($row) && is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $score = is_array($row) ? ($row['score'] ?? null) : null;
            $rank = is_array($row) ? ($row['rank'] ?? null) : null;
            $profileEligibility = is_array($eligibility[$key] ?? null) ? $eligibility[$key] : [];
            $eligible = is_array($row) && is_bool($row['eligible'] ?? null)
                ? $row['eligible']
                : (is_bool($profileEligibility['eligible'] ?? null) ? $profileEligibility['eligible'] : null);

            if ($key === ''
                || isset($seenKeys[$key])
                || $label === ''
                || ! is_numeric($score)
                || ($rank !== null && (! is_numeric($rank) || (int) $rank < 1))
                || ($rank !== null && $eligible === false)
                || ($rank === null && $eligible !== false && ! (($result['scoring_mode'] ?? null) === 'weighted' && ($result['no_score'] ?? false)))) {
                return [];
            }

            $seenKeys[$key] = true;
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'score' => ($result['scoring_mode'] ?? null) === 'weighted' ? (string) $score : $score + 0,
                'rank' => $rank === null ? null : (int) $rank,
                'recommended' => $key === $recommendedKey,
                'eligible' => $eligible,
                'reasons' => $this->reasons(
                    is_array($row) && array_key_exists('reasons', $row)
                        ? $row['reasons']
                        : ($profileEligibility['reasons'] ?? []),
                ),
            ];
        }

        return $rows;
    }

    /** Add weighted presentation metadata without changing legacy/simple rows. */
    private function weightedRows(array $rows, array $result): array
    {
        if (($result['scoring_mode'] ?? null) !== 'weighted') {
            return $rows;
        }
        foreach ($rows as &$row) {
            $percentage = $result['suitability_percentages'][$row['key']] ?? null;
            $row['raw_score'] = (string) $row['score'];
            $row['suitability_percentage'] = $percentage;
            $row['raw_score_label'] = $this->decimalLabel($row['raw_score']);
            $row['suitability_label'] = $percentage === null ? 'بدون امتیاز' : $this->decimalLabel((string) $percentage).'٪';
            $row['display_value'] = $row['suitability_label'].' — امتیاز خام: '.$row['raw_score_label'];
        }
        unset($row);

        return $rows;
    }

    /** Presentation uses only stored calculation snapshots, never the current form configuration. */
    public function weightedSummary(array $result): ?array
    {
        if (($result['scoring_mode'] ?? null) !== 'weighted') {
            return null;
        }
        $percentage = $result['suitability_percentages'][$result['recommended_method'] ?? ''] ?? null;

        return [
            'no_score' => ($result['no_score'] ?? false) === true,
            'suitability_label' => $percentage === null ? null : $this->decimalLabel((string) $percentage).'٪',
            'factors' => array_values(array_filter($result['top_factors'] ?? [], fn ($factor): bool => is_array($factor) && is_string($factor['label'] ?? null))),
            'decision_report' => is_array($result['decision_report'] ?? null) ? $result['decision_report'] : null,
        ];
    }

    private function decimalLabel(string $value): string
    {
        $rounded = CalculatorDecimal::rounded(CalculatorDecimal::value($value));

        return PersianDate::digits(str_replace('.', '٫', rtrim(rtrim($rounded, '0'), '.')));
    }

    /** @return list<array{rule_id: string, message: string}> */
    private function reasons(mixed $reasons): array
    {
        $normalized = [];

        foreach (is_array($reasons) ? $reasons : [] as $reason) {
            if (! is_array($reason)) {
                continue;
            }

            $ruleId = is_string($reason['rule_id'] ?? null) ? $reason['rule_id'] : '';
            $message = is_string($reason['message'] ?? null) ? trim($reason['message']) : '';

            if ($ruleId !== '' && $message !== '') {
                $normalized[] = ['rule_id' => $ruleId, 'message' => $message];
            }
        }

        return array_values(array_unique($normalized, SORT_REGULAR));
    }
}
