<?php

namespace App\Services;

use App\Models\FormSubmission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

final class CalculatorSubmissionReport
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly PersianPdfHtml $persianPdf,
    ) {}

    public function download(FormSubmission $submission): Response
    {
        File::ensureDirectoryExists(
            config('dompdf.options.font_dir', storage_path('fonts')),
        );

        $html = view('reports.calculator-submission', $this->data($submission))->render();
        $pdf = Pdf::loadHTML($this->persianPdf->shape($html))
            ->setPaper('a4');

        return $pdf->download("calculator-report-{$submission->getKey()}.pdf");
    }

    /**
     * Build the report exclusively from the immutable submission snapshots.
     * Site settings are presentation metadata and never affect the result.
     *
     * @return array<string, mixed>
     */
    public function data(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload) ? $submission->payload : [];
        $result = is_array($submission->calculation_result) ? $submission->calculation_result : [];

        return [
            'submission' => $submission,
            'brand' => [
                'name' => $this->settings->siteName(),
                'phone' => $this->settings->contactPhone(),
                'email' => $this->settings->contactEmail(),
                'address' => $this->settings->contactAddress(),
            ],
            'customer' => array_filter([
                'نام' => $this->scalar($payload['name'] ?? null),
                'شماره تماس' => $this->scalar($payload['phone'] ?? null),
                'ایمیل' => $this->scalar($payload['email'] ?? null),
            ], fn (?string $value): bool => filled($value)),
            'inputs' => $this->inputs($payload, $result),
            'recommendation' => $this->scalar($result['result'] ?? null),
            'scores' => $this->scores($result),
            'explanation' => $this->scalar($result['reason'] ?? $result['explanation'] ?? null),
            'summary' => $this->scalar($result['project_summary'] ?? $result['summary'] ?? null),
            'outputs' => $this->labelledValues($result['outputs'] ?? []),
            'benefits' => $this->scalarList($result['benefits'] ?? []),
            'generatedAt' => now(),
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function inputs(array $payload, array $result): array
    {
        $snapshot = $payload[SubmissionAnswerSnapshot::PAYLOAD_KEY] ?? null;

        if (is_array($snapshot) && $snapshot !== []) {
            $inputs = $this->snapshotInputs($snapshot);

            if ($inputs !== []) {
                return $inputs;
            }
        }

        $answerLabels = is_array($result['answer_labels'] ?? null) ? $result['answer_labels'] : [];
        $fieldLabels = is_array($result['answer_field_labels'] ?? null) ? $result['answer_field_labels'] : [];
        $inputs = [];

        foreach ($answerLabels as $key => $value) {
            if (($display = $this->scalar($value)) === null) {
                continue;
            }

            $inputs[] = [
                'label' => $this->scalar($fieldLabels[$key] ?? null) ?? 'ورودی پروژه '.(count($inputs) + 1),
                'value' => $display,
            ];
        }

        $excluded = ['name', 'phone', 'email', 'message', 'notes', ...array_keys($answerLabels)];

        foreach ($payload as $key => $value) {
            if (in_array($key, $excluded, true) || str_starts_with((string) $key, '_')) {
                continue;
            }

            if (($display = $this->scalar($value)) === null) {
                continue;
            }

            $inputs[] = [
                'label' => 'ورودی پروژه '.(count($inputs) + 1),
                'value' => $display,
            ];
        }

        return $inputs;
    }

    /** @return list<array{label: string, value: string}> */
    private function snapshotInputs(array $snapshot): array
    {
        $inputs = [];
        $excluded = ['name', 'phone', 'email', 'message', 'notes'];

        foreach ($snapshot as $answer) {
            if (! is_array($answer) || in_array($answer['field_key'] ?? null, $excluded, true)) {
                continue;
            }

            $label = $this->scalar($answer['field_label'] ?? null);
            $value = $this->scalar($answer['display_value'] ?? null);

            if ($label !== null && $value !== null) {
                $inputs[] = ['label' => $label, 'value' => $value];
            }
        }

        return $inputs;
    }

    /** @return list<array{label: string, value: int|float|string, recommended: bool}> */
    private function scores(array $result): array
    {
        $scores = is_array($result['scores'] ?? null) ? $result['scores'] : [];
        $labels = is_array($result['score_labels'] ?? null) ? $result['score_labels'] : [];
        $recommendedKey = $this->scalar($result['recommended_method'] ?? null);
        $recommendation = $this->scalar($result['result'] ?? null);
        $rows = [];

        foreach ($scores as $key => $score) {
            if (! is_scalar($score)) {
                continue;
            }

            $isRecommended = (string) $key === $recommendedKey;
            $rows[] = [
                'label' => $this->scalar($labels[$key] ?? null)
                    ?? ($isRecommended ? $recommendation : null)
                    ?? 'گزینه پیشنهادی '.(count($rows) + 1),
                'value' => $score,
                'recommended' => $isRecommended,
            ];
        }

        return $rows;
    }

    /** @return list<array{label: string, value: string}> */
    private function labelledValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $rows = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $label = $this->scalar($value['label'] ?? null);
            $display = $this->scalar($value['value'] ?? null);

            if ($label !== null && $display !== null) {
                $rows[] = ['label' => $label, 'value' => $display];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function scalarList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map($this->scalar(...), $values),
            fn (?string $value): bool => $value !== null,
        ));
    }

    private function scalar(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }

        if (is_array($value)) {
            $parts = array_values(array_filter(
                array_map($this->scalar(...), $value),
                fn (?string $part): bool => $part !== null,
            ));

            return $parts === [] ? null : implode('، ', $parts);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
