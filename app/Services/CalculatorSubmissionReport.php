<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Services\Calculators\CalculationResultRows;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

final class CalculatorSubmissionReport
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CalculationResultRows $calculationRows,
    ) {}

    public function download(FormSubmission $submission): Response
    {
        $html = view('reports.calculator-submission', $this->data($submission))->render();

        try {
            return Pdf::html($html)
                ->format('a4')
                ->margins(10, 10, 12, 10)
                ->waitUntilReady("document.fonts.status === 'loaded'", timeout: 30000)
                ->download("calculator-report-{$submission->getKey()}.pdf")
                ->toResponse(request());
        } catch (Throwable $exception) {
            Log::error('Calculator PDF renderer failed', [
                'driver' => config('laravel-pdf.driver'),
                'submission_id' => $submission->getKey(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_code' => $exception->getCode(),
                'chrome_binary' => config('laravel-pdf.chrome.chrome_binary'),
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'sockets_loaded' => extension_loaded('sockets'),
                'exception' => $exception,
            ]);

            abort(503, 'سرویس تولید گزارش PDF در دسترس نیست. لطفاً دوباره تلاش کنید.');
        }
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
            'recommendation' => $this->scalar($result['result_title'] ?? $result['result'] ?? null),
            'resultSummary' => $this->scalar($result['result_summary'] ?? null),
            'resultDescription' => $this->scalar($result['result_description'] ?? $result['description'] ?? null),
            'resultNote' => $this->scalar($result['result_note'] ?? null),
            'noEligibleRecommendation' => ($result['no_eligible_recommendation'] ?? false) === true,
            'weighted' => $this->calculationRows->weightedSummary($result),
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

    /** @return list<array{label: string, value: mixed, rank: int|null, recommended: bool, eligible: bool|null, reasons: list<string>}> */
    private function scores(array $result): array
    {
        return array_map(static fn (array $row): array => [
            'label' => $row['label'],
            'value' => $row['suitability_label'] ?? $row['score'],
            ...(array_key_exists('raw_score_label', $row) ? [
                'raw_score_label' => $row['raw_score_label'],
                'raw_score' => $row['raw_score'],
                'suitability_percentage' => $row['suitability_percentage'],
            ] : []),
            'rank' => $row['rank'],
            'recommended' => $row['recommended'],
            'eligible' => $row['eligible'],
            'reasons' => array_column($row['reasons'], 'message'),
        ], $this->calculationRows->fromSnapshot($result, 'گزینه پیشنهادی'));
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
