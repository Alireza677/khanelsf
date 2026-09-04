<?php

namespace App\Services\Calculators;

final class CalculationResultRows
{
    /**
     * @return list<array{key: string, label: string, score: mixed, rank: int|null, recommended: bool, eligible: bool|null, reasons: list<array{rule_id: string, message: string}>}>
     */
    public function fromSnapshot(array $result, string $fallbackLabel = 'نتیجه'): array
    {
        $ranking = $this->storedRanking($result);

        if ($ranking !== []) {
            return $ranking;
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

        return $rows;
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
                || ($rank === null && $eligible !== false)) {
                return [];
            }

            $seenKeys[$key] = true;
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'score' => $score + 0,
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
