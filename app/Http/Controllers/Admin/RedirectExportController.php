<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Services\RedirectCsvExporter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RedirectExportController extends Controller
{
    public function __invoke(RedirectCsvExporter $exporter): StreamedResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $filename = 'redirects-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($exporter): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $exporter->headings());

            Redirect::query()
                ->latest()
                ->chunk(100, function ($redirects) use ($handle, $exporter): void {
                    foreach ($redirects as $redirect) {
                        fputcsv($handle, $exporter->row($redirect));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
