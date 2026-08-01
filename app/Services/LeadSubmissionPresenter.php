<?php

namespace App\Services;

use App\Models\Lead;

final class LeadSubmissionPresenter
{
    public function __construct(private readonly FormSubmissionPresenter $submissions) {}

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
        $result = $this->calculationResult($lead);
        $scores = data_get($result, 'scores', []);

        if (! is_array($scores)) {
            return [];
        }

        $recommendations = data_get($result, 'score_labels', []);

        $recommendations = is_array($recommendations) ? $recommendations : [];
        $rows = [];

        foreach ($scores as $key => $score) {
            $rows[] = [
                'label' => filled($recommendations[$key] ?? null)
                    ? $recommendations[$key]
                    : 'نتیجه '.(count($rows) + 1),
                'value' => $this->displayValue($score),
            ];
        }

        return $rows;
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
