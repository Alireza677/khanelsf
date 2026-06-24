<?php

namespace App\Services;

use App\Models\Order;

class OrderCsvExporter
{
    public function headings(): array
    {
        return [
            'order_number',
            'customer_name',
            'customer_phone',
            'customer_email',
            'total',
            'status',
            'payment_status',
            'created_at',
        ];
    }

    public function row(Order $order): array
    {
        return [
            $order->order_number,
            $order->customer_name,
            $order->customer_phone,
            $order->customer_email,
            number_format((float) $order->total, 2, '.', ''),
            $order->status,
            $order->payment_status,
            $order->created_at?->toDateTimeString(),
        ];
    }
}
