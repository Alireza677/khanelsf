<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Services\Calculators\CalculatorManager;
use App\Services\FormNotifications\FormSubmissionNotificationDispatcher;
use Illuminate\Support\Facades\DB;

final class FormSubmissionService
{
    public function __construct(
        private readonly CalculatorManager $calculators,
        private readonly SubmissionAnswerSnapshot $snapshots,
        private readonly FormSubmissionNotificationDispatcher $notifications,
    ) {}

    public function submit(Form $form, array $payload, array $attribution = []): FormSubmission
    {
        $submission = DB::transaction(function () use ($form, $payload, $attribution): FormSubmission {
            $source = $this->stringOrDefault($attribution['source'] ?? null, 'website');
            $pageId = is_numeric($attribution['page_id'] ?? null) ? (int) $attribution['page_id'] : null;
            $pageUrl = $this->stringOrNull($attribution['page_url'] ?? null);
            $blockId = $this->blockId($attribution['block_id'] ?? null);
            $answerSnapshot = $this->snapshots->answers($form, $payload);

            $submission = $form->submissions()->create([
                'source' => $source,
                'page_id' => $pageId,
                'page_url' => $pageUrl,
                'block_id' => $blockId,
                'payload' => [
                    ...$payload,
                    SubmissionAnswerSnapshot::PAYLOAD_KEY => $answerSnapshot,
                ],
                'submitted_at' => now(),
            ]);

            $calculationResult = $form->isCalculator()
                ? $this->calculators->calculate($form, $payload)->toArray()
                : null;

            if ($calculationResult !== null) {
                $calculationResult = $this->snapshots->enrichCalculationResult(
                    $form,
                    $calculationResult,
                    $answerSnapshot,
                );
            }

            if ($calculationResult !== null) {
                $submission->update(['calculation_result' => $calculationResult]);
            }

            Lead::query()->create([
                'form_submission_id' => $submission->getKey(),
                'form_id' => $form->getKey(),
                'page_id' => $pageId,
                'name' => $this->stringOrNull($payload['name'] ?? null),
                'phone' => $this->stringOrNull($payload['phone'] ?? null),
                'email' => $this->stringOrNull($payload['email'] ?? null),
                'notes' => $this->stringOrNull($payload['message'] ?? $payload['notes'] ?? null),
                'calculation_result' => $calculationResult,
                'status' => 'new',
                'source' => $source,
                'page_url' => $pageUrl,
                'block_id' => $blockId,
            ]);

            return $submission->load('lead');
        });

        $this->notifications->dispatch($form, $submission);

        return $submission;
    }

    private function blockId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $value) === 1
            ? strtoupper($value)
            : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
