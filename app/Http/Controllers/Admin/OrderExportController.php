<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderCsvExporter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExportController extends Controller
{
    public function __invoke(OrderCsvExporter $exporter): StreamedResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $filename = 'orders-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($exporter): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $exporter->headings());

            Order::query()
                ->latest()
                ->chunk(100, function ($orders) use ($handle, $exporter): void {
                    foreach ($orders as $order) {
                        fputcsv($handle, $exporter->row($order));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
