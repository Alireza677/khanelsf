<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>گزارش نتیجه محاسبه</title>
    @php
        $vazirmatnRegular = base64_encode(file_get_contents(resource_path('fonts/vazirmatn/Vazirmatn-Regular.ttf')));
        $vazirmatnBold = base64_encode(file_get_contents(resource_path('fonts/vazirmatn/Vazirmatn-Bold.ttf')));
    @endphp
    <style>
        @font-face {
            font-family: Vazirmatn;
            font-style: normal;
            font-weight: 400;
            src: url("data:font/truetype;charset=utf-8;base64,{{ $vazirmatnRegular }}") format("truetype");
        }
        @font-face {
            font-family: Vazirmatn;
            font-style: normal;
            font-weight: 700;
            src: url("data:font/truetype;charset=utf-8;base64,{{ $vazirmatnBold }}") format("truetype");
        }
        @page { margin: 28px 32px 38px; }
        * { box-sizing: border-box; }
        html, body { direction: rtl; }
        body { color: #172033; font-family: Vazirmatn, DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.8; margin: 0; text-align: right; }
        h1, h2, p { margin-top: 0; }
        h1, h2, strong, b { font-family: Vazirmatn, DejaVu Sans, sans-serif; font-weight: 700; }
        .header { background: #0f3d5e; border-radius: 12px; color: #fff; padding: 22px 24px; }
        .brand { color: #b9e6ff; font-size: 10px; font-weight: bold; margin-bottom: 5px; }
        .header h1 { font-size: 22px; margin-bottom: 5px; }
        .header p { color: #dcecf5; margin-bottom: 0; }
        .meta { color: #657083; font-size: 9px; margin-top: 10px; }
        .hero { background: #eef8f3; border: 1px solid #b9dfcb; border-radius: 10px; margin-top: 18px; padding: 18px 22px; text-align: center; }
        .hero span { color: #4c6658; display: block; font-size: 10px; }
        .hero strong { color: #12623e; display: block; font-size: 24px; margin-top: 4px; }
        .section { border: 1px solid #e2e7ee; border-radius: 9px; margin-top: 14px; padding: 15px 18px; page-break-inside: avoid; }
        .section h2 { color: #173f5f; font-size: 14px; margin-bottom: 9px; }
        .customer-table,
        .inputs-table,
        .summary-table,
        .scores-table {
            border-collapse: collapse;
            direction: ltr;
            table-layout: fixed;
            width: 100%;
        }
        .customer-table td,
        .inputs-table td,
        .summary-table td,
        .scores-table td {
            border-bottom: 1px solid #edf0f4;
            padding: 6px 4px;
            vertical-align: top;
            white-space: normal;
            word-wrap: break-word;
        }
        .customer-table tr:last-child td,
        .inputs-table tr:last-child td,
        .summary-table tr:last-child td,
        .scores-table tr:last-child td { border-bottom: 0; }
        .report-table__label {
            color: #687386;
            direction: rtl;
            overflow-wrap: anywhere;
            text-align: right;
            width: 36%;
        }
        .report-table__value {
            direction: rtl;
            overflow-wrap: anywhere;
            text-align: right;
            width: 64%;
        }
        .scores-table .report-table__label { width: 76%; }
        .scores-table .report-table__value {
            direction: ltr;
            text-align: left;
            width: 24%;
        }
        .scores-table .recommended td { background: #f0faf5; color: #12623e; font-weight: bold; }
        ul { margin: 0; padding-right: 18px; }
        .footer { border-top: 1px solid #dce2e9; color: #697486; font-size: 9px; margin-top: 20px; padding-top: 10px; }
        .footer-contact { margin-top: 4px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">{{ $brand['name'] }}</div>
        <h1>گزارش نتیجه محاسبه</h1>
        <p>خلاصه اطلاعات ثبت‌شده و نتیجه نهایی ارزیابی</p>
    </header>

    <div class="meta">شماره گزارش: {{ $submission->getKey() }} | تاریخ ثبت: {{ \App\Support\PersianDate::dateTime($submission->submitted_at) }}</div>

    <section class="hero">
        <span>پیشنهاد مناسب برای شما</span>
        <strong>{{ $recommendation }}</strong>
        @if ($explanation)
            <p>{{ $explanation }}</p>
        @endif
    </section>

    @if ($customer !== [])
        <section class="section">
            <h2>اطلاعات مشتری</h2>
            <table class="customer-table">
                @foreach ($customer as $label => $value)
                    <tr>
                        <td class="report-table__value">{{ $value }}</td>
                        <td class="report-table__label">{{ $label }}</td>
                    </tr>
                @endforeach
            </table>
        </section>
    @endif

    @if ($inputs !== [])
        <section class="section">
            <h2>اطلاعات پروژه</h2>
            <table class="inputs-table">
                @foreach ($inputs as $input)
                    <tr>
                        <td class="report-table__value">{{ $input['value'] }}</td>
                        <td class="report-table__label">{{ $input['label'] }}</td>
                    </tr>
                @endforeach
            </table>
        </section>
    @endif

    @if ($summary || $outputs !== [])
        <section class="section">
            <h2>خلاصه پروژه</h2>
            @if ($summary)<p>{{ $summary }}</p>@endif
            @if ($outputs !== [])
                <table class="summary-table">
                    @foreach ($outputs as $output)
                        <tr>
                            <td class="report-table__value">{{ $output['value'] }}</td>
                            <td class="report-table__label">{{ $output['label'] }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </section>
    @endif

    @if ($scores !== [])
        <section class="section">
            <h2>خلاصه امتیازها</h2>
            <table class="scores-table">
                @foreach ($scores as $score)
                    <tr @class(['recommended' => $score['recommended']])>
                        <td class="report-table__value">{{ $score['value'] }}</td>
                        <td class="report-table__label">{{ $score['label'] }}</td>
                    </tr>
                @endforeach
            </table>
        </section>
    @endif

    @if ($benefits !== [])
        <section class="section">
            <h2>مزایا و توضیحات نتیجه</h2>
            <ul>
                @foreach ($benefits as $benefit)<li>{{ $benefit }}</li>@endforeach
            </ul>
        </section>
    @endif

    <footer class="footer">
        <div>تاریخ تولید گزارش: {{ \App\Support\PersianDate::dateTime($generatedAt) }}</div>
        <div class="footer-contact">
            راه‌های ارتباطی:
            {{ $brand['phone'] ?: 'شماره تماس مجموعه' }}
            @if ($brand['email']) | {{ $brand['email'] }} @endif
            @if ($brand['address']) | {{ $brand['address'] }} @endif
        </div>
    </footer>
</body>
</html>
