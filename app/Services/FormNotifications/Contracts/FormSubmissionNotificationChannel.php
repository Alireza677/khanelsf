<?php

namespace App\Services\FormNotifications\Contracts;

use App\Models\Form;
use App\Models\FormSubmission;

interface FormSubmissionNotificationChannel
{
    public function supports(array $settings): bool;

    public function send(Form $form, FormSubmission $submission, array $settings): void;
}
