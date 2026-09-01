<?php

namespace App\Support;

use App\Models\Form;

final class FormSubmitConfirmation
{
    public const INPUT_KEY = '_submit_confirmation';

    public const PAYLOAD_KEY = '_submit_confirmation_audit';

    public static function enabled(Form $form): bool
    {
        return (bool) data_get($form->settings, 'submit_confirmation_enabled', false);
    }

    public static function text(Form $form): string
    {
        $text = data_get($form->settings, 'submit_confirmation_text');

        return is_string($text) ? trim($text) : '';
    }
}
