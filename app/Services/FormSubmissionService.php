<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Services\Calculators\CalculatorManager;
use App\Services\FormNotifications\FormSubmissionNotificationDispatcher;
use App\Support\FormSubmitConfirmation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class FormSubmissionService
{
    public function __construct(
        private readonly CalculatorManager $calculators,
        private readonly SubmissionAnswerSnapshot $snapshots,
        private readonly FormSubmissionNotificationDispatcher $notifications,
    ) {}

    public function submit(Form $form, array $payload, array $attribution = [], array $files = [], ?array $confirmationAudit = null): FormSubmission
    {
        $this->calculators->assertConfiguration($form);
        $storedPaths = [];

        try {
            $submission = DB::transaction(function () use ($form, $payload, $attribution, $files, $confirmationAudit, &$storedPaths): FormSubmission {
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
                        ...($confirmationAudit === null ? [] : [
                            FormSubmitConfirmation::PAYLOAD_KEY => [
                                ...$confirmationAudit,
                                'confirmed_at' => now()->toIso8601String(),
                            ],
                        ]),
                    ],
                    'submitted_at' => now(),
                ]);

                foreach ($files as $fieldKey => $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $extension = strtolower($file->getClientOriginalExtension());
                    $storedPath = "form-submissions/{$submission->getKey()}/".Str::uuid().".{$extension}";

                    if (! Storage::disk('local')->putFileAs(
                        dirname($storedPath),
                        $file,
                        basename($storedPath),
                    )) {
                        throw new RuntimeException('Unable to store form submission attachment.');
                    }

                    $storedPaths[] = $storedPath;
                    $submission->attachments()->create([
                        'field_key' => $fieldKey,
                        'stored_path' => $storedPath,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => (string) $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }

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

                if ($form->generatesLeads()) {
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
                }

                return $submission->load(['lead', 'attachments']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

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
