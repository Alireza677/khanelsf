<?php

namespace App\Services\FormNotifications;

use App\Mail\FormSubmissionAdminMail;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormNotifications\Contracts\FormSubmissionNotificationChannel;
use Illuminate\Support\Facades\Mail;

final class EmailFormSubmissionNotificationChannel implements FormSubmissionNotificationChannel
{
    public function supports(array $settings): bool
    {
        return filter_var($settings['notify_admin'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filter_var($settings['email'] ?? null, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(Form $form, FormSubmission $submission, array $settings): void
    {
        Mail::to((string) $settings['email'])->send(new FormSubmissionAdminMail($submission));
    }
}
