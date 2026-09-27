<?php

namespace App\Services;

use App\Models\Lead;
use App\Services\Calculators\CalculationResultRows;

final class LeadSubmissionPresenter
{
    public function __construct(
        private readonly FormSubmissionPresenter $submissions,
        private readonly CalculationResultRows $calculationRows,
    ) {}

    public function answers(Lead $lead): array
    {
        return $lead->submission
            ? $this->submissions->answers($lead->submission)
            : [];
    }

    public function calculationResult(Lead $lead): array
    {
        $result = $lead->calculation_result ?: $lead->submission?->calculation_result;

        return is_array($result) ? $result : [];
    }

    public function scores(Lead $lead): array
    {
        return array_map(fn (array $row): array => [
            'label' => $row['label'],
            'value' => $row['display_value'] ?? $this->displayValue($row['score']),
            ...(array_key_exists('suitability_percentage', $row) ? ['raw_score' => $row['raw_score'], 'suitability_percentage' => $row['suitability_percentage']] : []),
            'rank' => $row['rank'],
            'eligible' => $row['eligible'],
            'eligibility_label' => match ($row['eligible']) {
                true => 'واجد شرایط',
                false => 'خارج‌شده',
                null => 'ارزیابی‌نشده',
            },
            'reason_text' => collect($row['reasons'])->pluck('message')->implode('، '),
        ], $this->calculationRows->fromSnapshot($this->calculationResult($lead)));
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => $this->displayValue($item))
                ->implode('، ');
        }

        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return '—';
    }
}
