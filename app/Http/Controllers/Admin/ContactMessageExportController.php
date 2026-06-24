<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\ContactMessageCsvExporter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactMessageExportController extends Controller
{
    public function __invoke(ContactMessageCsvExporter $exporter): StreamedResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $filename = 'contact-messages-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($exporter): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $exporter->headings());

            ContactMessage::query()
                ->latest()
                ->chunk(100, function ($messages) use ($handle, $exporter): void {
                    foreach ($messages as $message) {
                        fputcsv($handle, $exporter->row($message));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
