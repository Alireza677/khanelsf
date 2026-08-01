<?php

namespace App\CMS\Actions\Normalizers;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Enums\CoreActionType;

final class ActionDestinationNormalizer
{
    public function normalize(array|ActionDestination $input): ActionDestination
    {
        $data = $input instanceof ActionDestination ? $input->toArray() : $input;
        $schemaVersion = $this->schemaVersion($data['schema_version'] ?? null);
        $typeKey = $this->stringOrNull($data['type'] ?? null);
        $type = CoreActionType::fromInput($typeKey);
        $openInNewTab = $this->boolean($data['open_in_new_tab'] ?? false);

        if ($type === null) {
            return new ActionDestination(
                type: $typeKey,
                openInNewTab: false,
                schemaVersion: $schemaVersion,
            );
        }

        if (! $type->allowsNewTab()) {
            $openInNewTab = false;
        }

        return match ($type) {
            CoreActionType::Page,
            CoreActionType::Project,
            CoreActionType::Product,
            CoreActionType::Service => new ActionDestination(
                type: $type->value,
                referenceId: $this->positiveInteger($data['reference_id'] ?? null),
                openInNewTab: $openInNewTab,
                schemaVersion: $schemaVersion,
            ),
            CoreActionType::Form => new ActionDestination(
                type: $type->value,
                referenceId: $this->positiveInteger($data['reference_id'] ?? null),
                display: $this->stringOrNull($data['display'] ?? null),
                openInNewTab: false,
                schemaVersion: $schemaVersion,
            ),
            CoreActionType::CustomUrl => new ActionDestination(
                type: $type->value,
                value: $this->stringOrNull($data['value'] ?? null),
                openInNewTab: $openInNewTab,
                schemaVersion: $schemaVersion,
            ),
            CoreActionType::Anchor => new ActionDestination(
                type: $type->value,
                value: $this->anchor($data['value'] ?? null),
                openInNewTab: false,
                schemaVersion: $schemaVersion,
            ),
            CoreActionType::Email => new ActionDestination(
                type: $type->value,
                value: $this->withoutPrefix($data['value'] ?? null, 'mailto:'),
                openInNewTab: false,
                schemaVersion: $schemaVersion,
            ),
            CoreActionType::Phone => new ActionDestination(
                type: $type->value,
                value: $this->phone($data['value'] ?? null),
                openInNewTab: false,
                schemaVersion: $schemaVersion,
            ),
        };
    }

    private function schemaVersion(mixed $value): int
    {
        return is_numeric($value) && (int) $value > 0
            ? (int) $value
            : ActionDestination::SCHEMA_VERSION;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $integer === false ? null : $integer;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value, " \t");

        return $value !== '' ? $value : null;
    }

    private function anchor(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        return $this->stringOrNull(ltrim($value, '#'));
    }

    private function withoutPrefix(mixed $value, string $prefix): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        if (str_starts_with(strtolower($value), $prefix)) {
            $value = substr($value, strlen($prefix));
        }

        return $this->stringOrNull($value);
    }

    private function phone(mixed $value): ?string
    {
        $value = $this->withoutPrefix($value, 'tel:');

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[ \t\x{00A0}().-]+/u', '', $value);

        return $this->stringOrNull($value);
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1') {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['true', 'yes', 'on'], true);
    }
}
