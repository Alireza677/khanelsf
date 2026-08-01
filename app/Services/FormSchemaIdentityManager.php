<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Str;

final class FormSchemaIdentityManager
{
    private Closure $idFactory;

    public function __construct(?Closure $idFactory = null)
    {
        $this->idFactory = $idFactory ?? fn (): string => (string) Str::ulid();
    }

    public function canonicalize(array $fields): array
    {
        $seenFieldIds = [];
        $seenFieldKeys = [];
        $seenOptionIds = [];

        foreach ($fields as $fieldIndex => $field) {
            if (! is_array($field)) {
                continue;
            }

            $field['field_id'] = $this->uniqueId($field['field_id'] ?? null, $seenFieldIds);
            $field['key'] = $this->uniqueMachineKey(
                $field['key'] ?? $field['name'] ?? null,
                $field['label'] ?? null,
                'field',
                $seenFieldKeys,
            );
            unset($field['name']);

            if (is_array($field['options'] ?? null)) {
                $field['options'] = $this->canonicalizeOptions($field['options'], $seenOptionIds);
            }

            $fields[$fieldIndex] = $field;
        }

        return $fields;
    }

    private function canonicalizeOptions(array $options, array &$seenOptionIds): array
    {
        $canonical = [];
        $seenValues = [];

        foreach ($options as $legacyValue => $option) {
            if (is_string($option)) {
                $option = [
                    'value' => is_string($legacyValue) ? $legacyValue : null,
                    'label' => $option,
                ];
            }

            if (! is_array($option)) {
                continue;
            }

            $option['option_id'] = $this->uniqueId($option['option_id'] ?? null, $seenOptionIds);
            $option['value'] = $this->uniqueOptionValue(
                $option['value'] ?? (is_string($legacyValue) ? $legacyValue : null),
                $option['label'] ?? null,
                $seenValues,
            );
            $canonical[] = $option;
        }

        return $canonical;
    }

    private function uniqueId(mixed $candidate, array &$seen): string
    {
        $identityKey = is_string($candidate) ? strtoupper($candidate) : null;

        if (! $this->isValidId($candidate) || isset($seen[$identityKey])) {
            do {
                $candidate = strtoupper((string) ($this->idFactory)());
                $identityKey = strtoupper($candidate);
            } while (! $this->isValidId($candidate) || isset($seen[$identityKey]));
        }

        $seen[$identityKey] = true;

        return $candidate;
    }

    private function uniqueMachineKey(mixed $candidate, mixed $label, string $fallback, array &$seen): string
    {
        $base = $this->isMachineKey($candidate)
            ? $candidate
            : $this->machineKey($label, $fallback);

        return $this->uniqueWithSuffix($base, $seen);
    }

    private function uniqueOptionValue(mixed $candidate, mixed $label, array &$seen): string
    {
        if (is_string($candidate) && trim($candidate) !== '' && ! isset($seen[$candidate])) {
            $seen[$candidate] = true;

            return $candidate;
        }

        $base = $this->isMachineKey($candidate)
            ? $candidate
            : $this->machineKey($label, 'option');

        return $this->uniqueWithSuffix($base, $seen);
    }

    private function uniqueWithSuffix(string $base, array &$seen): string
    {
        $candidate = $base;
        $suffix = 2;

        while (isset($seen[$candidate])) {
            $candidate = "{$base}_{$suffix}";
            $suffix++;
        }

        $seen[$candidate] = true;

        return $candidate;
    }

    private function machineKey(mixed $label, string $fallback): string
    {
        $key = is_string($label) ? Str::slug($label, '_') : '';
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_]+/', '', $key) ?? '';
        $key = trim($key, '_');

        if ($key === '' || preg_match('/^[a-z]/', $key) !== 1) {
            return $fallback;
        }

        return $key;
    }

    private function isMachineKey(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z][a-z0-9_]*$/', $value) === 1;
    }

    private function isValidId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', strtoupper($id)) === 1;
    }
}
