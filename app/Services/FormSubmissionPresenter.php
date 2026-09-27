<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\FormSubmissionAttachment;
use App\Services\Calculators\CalculationResultRows;
use Illuminate\Support\Collection;

final class FormSubmissionPresenter
{
    public function __construct(
        private readonly FormSchema $schema,
        private readonly CalculationResultRows $calculationRows,
    ) {}

    /** @return list<array{label: string, value: string}> */
    public function answers(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload) ? $submission->payload : [];
        $snapshot = $this->snapshotAnswers($payload);

        if ($snapshot !== []) {
            return [...$snapshot, ...$this->attachmentAnswers($submission)];
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

        return [...$answers, ...$this->attachmentAnswers($submission)];
    }

    public function answerGroups(FormSubmission $submission): array
    {
        $submission->loadMissing(['form', 'attachments']);
        $payload = is_array($submission->payload) ? $submission->payload : [];
        $snapshot = collect($payload[SubmissionAnswerSnapshot::PAYLOAD_KEY] ?? [])->keyBy('field_key');
        $values = $this->submittedFields($payload);
        $files = $submission->attachmentsByField();
        $groups = [];
        $group = null;
        $known = [];
        $number = 0;

        foreach ($submission->form ? $this->schema->fields($submission->form) : [] as $field) {
            if (in_array($field['type'], ['page', 'step'], true)) {
                $number++;
                $title = filled($field['label']) && $field['label'] !== 'مرحله جدید' ? $field['label'] : 'بخش '.$number;
                $groups[] = ['title' => $title, 'answers' => []];
                $group = array_key_last($groups);

                continue;
            }
            $key = $field['name'];
            $known[$key] = true;
            if (! array_key_exists($key, $values) && ! $snapshot->has($key) && ! $files->has($key)) {
                continue;
            }
            if ($group === null) {
                $groups[] = ['title' => 'اطلاعات فرم', 'answers' => []];
                $group = 0;
            }
            $saved = $snapshot->get($key, []);
            $groups[$group]['answers'][] = $this->answerItem($key, $saved['field_label'] ?? $field['label'],
                $saved['display_value'] ?? ($values[$key] ?? null), $field['type'], $files->get($key, collect()));
        }

        foreach (collect([...array_keys($values), ...$snapshot->keys(), ...$files->keys()])->unique() as $key) {
            if (isset($known[$key])) {
                continue;
            }
            $fallback = collect($groups)->search(fn ($item) => $item['title'] === 'اطلاعات فرم');
            if ($fallback === false) {
                $groups[] = ['title' => 'اطلاعات فرم', 'answers' => []];
                $fallback = array_key_last($groups);
            }
            $saved = $snapshot->get($key, []);
            $groups[$fallback]['answers'][] = $this->answerItem($key, $saved['field_label'] ?? $key,
                $saved['display_value'] ?? ($values[$key] ?? null), 'text', $files->get($key, collect()));
        }

        return collect($groups)->filter(fn ($item) => $item['answers'] !== [])
            ->map(fn ($item) => (object) [...$item, 'count' => count($item['answers'])])->values()->all();
    }

    public function files(FormSubmission $submission): array
    {
        $submission->loadMissing('attachments');

        return $submission->attachments->map(fn ($file) => $this->fileItem($file))->all();
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

    /** @return list<array{label: string, value: string, rank: int|null, eligible: bool|null, eligibility_label: string, reason_text: string}> */
    public function calculationScores(FormSubmission $submission): array
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
        ], $this->calculationRows->fromSnapshot($this->calculationResult($submission)));
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

    private function answerItem(string $key, string $label, mixed $value, string $type, Collection $attachments): object
    {
        $display = $this->displayValue($value);

        return (object) [
            'key' => $key, 'label' => $label, 'value' => $display,
            'wide' => $type === 'textarea' || mb_strlen($display) > 140,
            'attachments' => $attachments->map(fn ($file) => $this->fileItem($file))->values()->all(),
        ];
    }

    private function fileItem(FormSubmissionAttachment $file): object
    {
        $mime = strtolower((string) $file->mime_type);

        return (object) [
            'name' => $file->original_name,
            'extension' => strtoupper(pathinfo($file->original_name, PATHINFO_EXTENSION) ?: 'FILE'),
            'size' => $this->fileSize((int) $file->size),
            'is_image' => str_starts_with($mime, 'image/'),
            'can_view' => str_starts_with($mime, 'image/') || $mime === 'application/pdf',
            'view_url' => route('admin.form-submission-attachments.view', $file),
            'download_url' => route('admin.form-submission-attachments.download', $file),
        ];
    }

    private function fileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return max(0, $bytes).' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }

    /** @return list<array{label: string, value: string}> */
    private function attachmentAnswers(FormSubmission $submission): array
    {
        $submission->loadMissing(['attachments', 'form']);
        $labels = collect($submission->form ? $this->schema->fields($submission->form) : [])
            ->mapWithKeys(fn (array $field): array => [$field['name'] => $field['label']]);

        return $submission->attachmentsByField()
            ->map(fn ($attachments, string $fieldKey): array => [
                'label' => (string) ($labels[$fieldKey] ?? $fieldKey),
                'value' => $attachments->pluck('original_name')->implode('، '),
            ])
            ->values()
            ->all();
    }
}
