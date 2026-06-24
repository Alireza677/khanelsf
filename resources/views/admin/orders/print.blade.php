<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>سفارش {{ $order->order_number }}</title>
    <style>
        body {
            color: #111827;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
            margin: 0;
            padding: 2rem;
        }

        .sheet {
            margin: 0 auto;
            max-width: 900px;
        }

        .header,
        .meta-grid,
        .totals {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr 1fr;
        }

        .header {
            align-items: start;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
        }

        .logo {
            max-height: 64px;
            max-width: 220px;
        }

        h1,
        h2,
        p {
            margin: 0 0 .5rem;
        }

        h1 {
            font-size: 1.8rem;
        }

        h2 {
            font-size: 1rem;
            margin-top: 1.5rem;
        }

        table {
            border-collapse: collapse;
            margin-top: 1rem;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: .65rem;
            text-align: right;
        }

        th {
            background: #f8fafc;
            font-size: .85rem;
            text-transform: uppercase;
        }

        .right {
            text-align: right;
        }

        .muted {
            color: #64748b;
        }

        .print-actions {
            margin-bottom: 1rem;
            text-align: right;
        }

        .print-actions button {
            background: #2563eb;
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            padding: .55rem .9rem;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <main class="sheet">
        <div class="print-actions">
            <button type="button" onclick="window.print()">چاپ</button>
        </div>

        <header class="header">
            <div>
                @if ($logoUrl)
                    <img class="logo" src="{{ $logoUrl }}" alt="{{ $siteName }}">
                @else
                    <h1>{{ $siteName }}</h1>
                @endif
                <p class="muted">فاکتور سفارش</p>
            </div>

            <div class="right">
                <h1>{{ $order->order_number }}</h1>
                <p>تاریخ: {{ $order->created_at?->format('Y-m-d H:i') }}</p>
                <p>وضعیت: {{ ucfirst($order->status) }}</p>
                <p>پرداخت: {{ ucfirst($order->payment_status) }}</p>
            </div>
        </header>

        <section class="meta-grid">
            <div>
                <h2>مشتری</h2>
                <p>{{ $order->customer_name }}</p>
                <p>{{ $order->customer_phone }}</p>
                @if ($order->customer_email)
                    <p>{{ $order->customer_email }}</p>
                @endif
                @if ($order->customer_address)
                    <p>{{ $order->customer_address }}</p>
                @endif
            </div>

            <div>
                <h2>سفارش</h2>
                <p>روش پرداخت: {{ ucfirst($order->payment_method) }}</p>
                <p>جمع کل: {{ number_format((float) $order->subtotal) }} تومان</p>
                <p>مبلغ نهایی: {{ number_format((float) $order->total) }} تومان</p>
            </div>
        </section>

        <section>
            <h2>آیتم‌ها</h2>
            <table>
                <thead>
                    <tr>
                        <th>آیتم</th>
                        <th>شناسه</th>
                        <th class="right">قیمت واحد</th>
                        <th class="right">تعداد</th>
                        <th class="right">مجموع</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->product_title }}</td>
                            <td>{{ $item->product_sku ?: '-' }}</td>
                            <td class="right">{{ number_format((float) $item->unit_price) }} تومان</td>
                            <td class="right">{{ $item->quantity }}</td>
                            <td class="right">{{ number_format((float) $item->total) }} تومان</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="right">مبلغ کل</th>
                        <th class="right">{{ number_format((float) $order->total) }} تومان</th>
                    </tr>
                </tfoot>
            </table>
        </section>

        @if ($order->notes || $order->admin_note)
            <section>
                @if ($order->notes)
                    <h2>توضیحات مشتری</h2>
                    <p>{{ $order->notes }}</p>
                @endif

                @if ($order->admin_note)
                    <h2>یادداشت داخلی مدیر</h2>
                    <p>{{ $order->admin_note }}</p>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
