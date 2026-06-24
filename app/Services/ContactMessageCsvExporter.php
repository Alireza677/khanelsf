<?php

namespace App\Services;

use App\Models\ContactMessage;

class ContactMessageCsvExporter
{
    public function headings(): array
    {
        return [
            'name',
            'email',
            'phone',
            'subject',
            'status',
            'created_at',
        ];
    }

    public function row(ContactMessage $message): array
    {
        return [
            $message->name,
            $message->email,
            $message->phone,
            $message->subject,
            $message->status,
            $message->created_at?->toDateTimeString(),
        ];
    }
}
