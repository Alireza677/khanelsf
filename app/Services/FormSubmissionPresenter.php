<?php

namespace App\Services;

use App\Models\FormSubmission;

final class FormSubmissionPresenter
{
    /** @return list<array{label: string, value: string}> */
    public function answers(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload) ? $submission->payload : [];
        $snapshot = $this->snapshotAnswers($payload);

        if ($snapshot !== []) {
            return $snapshot;
        }

        $fieldLabels = data_get($submission->calculation_result, 'answer_field_labels', []);
        $fieldLabels = is_array($fieldLabels) ? $fieldLabels : [];
        $answerLabels = data_get($submission->calculation_result, 'answer_labels', []);
        $answerLabels = is_array($answerLabels) ? $answerLabels : [];
        $answers = [];

        foreach ($this->submittedFields($payload) as $key => $value) {
            if (! array_key_exists($key, $fieldLabels) && ! array_key_exists($key, $answerLabels)) {
                continue;
            }

            $answers[] = [
                'label' => filled($fieldLabels[$key] ?? null)
                    ? (string) $fieldLabels[$key]
                    : 'پاسخ '.(count($answers) + 1),
                'value' => array_key_exists($key, $answerLabels)
                    ? $this->displayValue($answerLabels[$key])
                    : $this->displayValue($value),
            ];
        }

        return $answers;
    }

    /** @return array<string, string> */
    public function rawFields(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload) ? $submission->payload : [];

        return collect($this->submittedFields($payload))
            ->map(fn (mixed $value): string => $this->displayValue($value))
            ->all();
    }

    public function calculationResult(FormSubmission $submission): array
    {
        return is_array($submission->calculation_result) ? $submission->calculation_result : [];
    }

    /** @return list<array{label: string, value: string}> */
    public function calculationScores(FormSubmission $submission): array
    {
        $result = $this->calculationResult($submission);
        $scores = data_get($result, 'scores', []);

        if (! is_array($scores)) {
            return [];
        }

        $labels = data_get($result, 'score_labels', []);

        $labels = is_array($labels) ? $labels : [];

        return collect($scores)
            ->map(fn (mixed $score, string|int $key): array => [
                'label' => filled($labels[$key] ?? null)
                    ? (string) $labels[$key]
                    : 'نتیجه '.(array_search($key, array_keys($scores), true) + 1),
                'value' => $this->displayValue($score),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function submittedFields(array $payload): array
    {
        return collect($payload)
            ->reject(fn (mixed $value, mixed $key): bool => ! is_string($key) || str_starts_with($key, '_'))
            ->all();
    }

    /** @return list<array{label: string, value: string}> */
    private function snapshotAnswers(array $payload): array
    {
        $snapshot = $payload[SubmissionAnswerSnapshot::PAYLOAD_KEY] ?? null;

        if (! is_array($snapshot)) {
            return [];
        }

        return collect($snapshot)
            ->filter(fn (mixed $answer): bool => is_array($answer) && filled($answer['field_label'] ?? null))
            ->map(fn (array $answer): array => [
                'label' => (string) $answer['field_label'],
                'value' => $this->displayValue($answer['display_value'] ?? null),
            ])
            ->values()
            ->all();
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '—';
            }

            return collect($value)
                ->map(fn (mixed $item, mixed $key): string => is_string($key)
                    ? "{$key}: ".$this->displayValue($item)
                    : $this->displayValue($item))
                ->implode('، ');
        }

        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return '—';
    }
}
