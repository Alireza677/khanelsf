<?php

namespace App\Services;

use App\Models\FormSubmission;

final class FormSubmissionSubmitterResolver
{
    public function resolve(FormSubmission|array $submission): string
    {
        $payload = $this->payload($submission);

        foreach (['name', 'full_name', 'customer_name'] as $key) {
            if ($value = $this->string($payload[$key] ?? null)) {
                return $value;
            }
        }

        $fullName = collect([
            $this->string($payload['first_name'] ?? null),
            $this->string($payload['last_name'] ?? null),
        ])->filter()->implode(' ');

        if ($fullName !== '') {
            return $fullName;
        }

        return $this->email($submission)
            ?? $this->phone($submission)
            ?? 'بدون نام';
    }

    public function email(FormSubmission|array $submission): ?string
    {
        return $this->string($this->payload($submission)['email'] ?? null);
    }

    public function phone(FormSubmission|array $submission): ?string
    {
        return $this->string($this->payload($submission)['phone'] ?? null);
    }

    private function payload(FormSubmission|array $submission): array
    {
        if ($submission instanceof FormSubmission) {
            return is_array($submission->payload) ? $submission->payload : [];
        }

        return $submission;
    }

    private function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
