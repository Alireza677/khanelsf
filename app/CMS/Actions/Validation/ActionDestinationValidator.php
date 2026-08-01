<?php

namespace App\CMS\Actions\Validation;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ActionValidationResult;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Enums\FormDisplay;

final class ActionDestinationValidator
{
    public function validate(ActionDestination $destination): ActionValidationResult
    {
        $errors = [];

        if ($destination->schemaVersion !== ActionDestination::SCHEMA_VERSION) {
            $errors['schema_version'][] = 'unsupported_schema_version';
        }

        $type = $destination->coreType();

        if ($type === null) {
            $errors['type'][] = filled($destination->type)
                ? 'unsupported_action_type'
                : 'action_type_required';

            return new ActionValidationResult($errors);
        }

        if ($type->usesReference() && $destination->referenceId === null) {
            $errors['reference_id'][] = 'positive_reference_id_required';
        }

        if ($type->usesValue() && $destination->value === null) {
            $errors['value'][] = 'action_value_required';
        }

        if ($destination->openInNewTab && ! $type->allowsNewTab()) {
            $errors['open_in_new_tab'][] = 'new_tab_not_supported';
        }

        match ($type) {
            CoreActionType::CustomUrl => $this->validateCustomUrl($destination->value, $errors),
            CoreActionType::Anchor => $this->validateAnchor($destination->value, $errors),
            CoreActionType::Email => $this->validateEmail($destination->value, $errors),
            CoreActionType::Phone => $this->validatePhone($destination->value, $errors),
            CoreActionType::Form => $this->validateFormDisplay($destination->display, $errors),
            default => null,
        };

        return new ActionValidationResult($errors);
    }

    private function validateCustomUrl(?string $value, array &$errors): void
    {
        if ($value === null) {
            return;
        }

        if ($this->hasControlCharacters($value) || preg_match('/\s/u', $value) === 1) {
            $errors['value'][] = 'url_contains_unsafe_characters';

            return;
        }

        if ($value === '#') {
            return;
        }

        if (str_starts_with($value, '#')) {
            $errors['value'][] = 'anchor_requires_anchor_type';

            return;
        }

        if (str_starts_with($value, '//') || str_contains($value, '\\')) {
            $errors['value'][] = 'protocol_relative_or_backslash_url_not_allowed';

            return;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches) === 1) {
            $scheme = strtolower($matches[1]);

            if (! in_array($scheme, ['http', 'https'], true)) {
                $errors['value'][] = 'unsafe_or_unsupported_url_scheme';

                return;
            }

            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                $errors['value'][] = 'invalid_absolute_url';
            }

            return;
        }

        if (! str_starts_with($value, '/')
            && ! str_starts_with($value, '?')
            && preg_match('/^[^\s?#][^\s#]*$/u', $value) !== 1) {
            $errors['value'][] = 'invalid_relative_url';
        }
    }

    private function validateAnchor(?string $value, array &$errors): void
    {
        if ($value !== null && preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $value) !== 1) {
            $errors['value'][] = 'invalid_anchor';
        }
    }

    private function validateEmail(?string $value, array &$errors): void
    {
        if ($value !== null && (
            $this->hasControlCharacters($value)
            || filter_var($value, FILTER_VALIDATE_EMAIL) === false
        )) {
            $errors['value'][] = 'invalid_email';
        }
    }

    private function validatePhone(?string $value, array &$errors): void
    {
        if ($value !== null && preg_match('/^\+?[0-9]{3,20}$/', $value) !== 1) {
            $errors['value'][] = 'invalid_phone';
        }
    }

    private function validateFormDisplay(?string $display, array &$errors): void
    {
        if ($display !== null && FormDisplay::tryFrom($display) === null) {
            $errors['display'][] = 'invalid_form_display';
        }
    }

    private function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }
}
