<?php

namespace App\Mail;

use App\Models\FormSubmission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FormSubmissionAdminMail extends Mailable
{
    public function __construct(public readonly FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ورودی جدید فرم: '.($this->submission->form?->name ?? 'فرم'),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.form-submission-admin');
    }
}
