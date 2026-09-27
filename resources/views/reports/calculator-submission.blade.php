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
        /* Keep margins in sync with CalculatorSubmissionReport::download(). */
        @page { size: A4; margin: 10mm 10mm 12mm; }
        * { box-sizing: border-box; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
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
        .avoid-break, .header, .hero, .section, .footer, tr { break-inside: avoid; page-break-inside: avoid; }
        h1, h2 { break-after: avoid; }
        p, li { orphans: 3; widows: 3; }
        .result-content { white-space: pre-line; overflow-wrap: anywhere; }
        .section { border: 1px solid #e2e7ee; border-radius: 9px; margin-top: 14px; padding: 15px 18px; }
        .section h2 { color: #173f5f; font-size: 14px; margin-bottom: 9px; }
        .decision-report { break-inside: auto; page-break-inside: auto; }
        .decision-report__factor { border-top: 1px solid #e2e7ee; margin-top: 10px; padding-top: 10px; break-inside: avoid; page-break-inside: avoid; }
        .decision-report__factor h3 { color: #173f5f; font-size: 12px; margin: 0 0 5px; break-after: avoid; }
        .decision-report__factor p { white-space: pre-line; overflow-wrap: anywhere; margin-bottom: 0; }
        .decision-report__summary { border-top: 1px solid #e2e7ee; margin: 12px 0 0; padding-top: 10px; }
        .customer-table,
        .inputs-table,
        .summary-table,
        .scores-table {
            border-collapse: collapse;
            direction: rtl;
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
    @php
        $eligibleScores = array_values(array_filter($scores, fn (array $score): bool => $score['eligible'] !== false));
        $excludedScores = array_values(array_filter($scores, fn (array $score): bool => $score['eligible'] === false));
    @endphp
    <header class="header">
        <div class="brand">{{ $brand['name'] }}</div>
        <h1>گزارش نتیجه محاسبه</h1>
        <p>خلاصه اطلاعات ثبت‌شده و نتیجه نهایی ارزیابی</p>
    </header>

    <div class="meta">شماره گزارش: <bdi dir="ltr">{{ $submission->getKey() }}</bdi> | تاریخ ثبت: <bdi dir="auto">{{ \App\Support\PersianDate::dateTime($submission->submitted_at) }}</bdi></div>

    <section class="hero">
        @if ($noEligibleRecommendation)
            <span>نیازمند بررسی کارشناسی</span>
            <strong>هیچ گزینه واجد شرایطی یافت نشد</strong>
            <p>بر اساس پاسخ‌های ثبت‌شده، هیچ‌یک از گزینه‌ها شرایط لازم را ندارند.</p>
        @elseif ($weighted && $weighted['no_score'])
            <strong>امتیازی برای مقایسه محاسبه نشد</strong>
            <p>وزن معیارهای تصمیم‌گیری صفر است؛ امکان تعیین میزان تطابق و پیشنهاد اصلی وجود ندارد.</p>
        @else
            <span>{{ $weighted ? 'پیشنهاد اصلی بر اساس پاسخ‌های شما' : 'پیشنهاد مناسب برای شما' }}</span>
            <strong><bdi dir="auto">{{ $recommendation }}</bdi></strong>
            @if (filled($resultSummary ?? null))<p class="result-content">{{ $resultSummary }}</p>@endif
            @if ($weighted && $weighted['suitability_label'] !== null)<p>میزان تطابق: <bdi dir="auto">{{ $weighted['suitability_label'] }}</bdi></p>@endif
        @endif
        @if (! $noEligibleRecommendation && $explanation)
            <p>{{ $explanation }}</p>
        @endif
    </section>

    @if ($weighted)
        <p>این ارزیابی بر اساس پاسخ‌ها و معیارها، وزن‌ها و امتیازهای تعریف‌شده در فرم تهیه شده است.</p>
        @if ($decisionReport = $weighted['decision_report'] ?? null)
            <section class="section decision-report" dir="rtl">
                <h2>چرا این پیشنهاد برای شما مناسب‌تر است؟</h2>
                <p>{{ $decisionReport['intro'] }}</p>
                @foreach ($decisionReport['factors'] as $factor)
                    <div class="decision-report__factor">
                        <h3>{{ $factor['label'] }}</h3>
                        <p>{{ $factor['explanation'] }}</p>
                    </div>
                @endforeach
                <p class="decision-report__summary">{{ $decisionReport['summary'] }}</p>
            </section>
        @elseif ($weighted['factors'] !== [])
            <section class="section">
                <h2>مهم‌ترین عوامل مؤثر در این نتیجه</h2>
                <ul>@foreach ($weighted['factors'] as $factor)<li>{{ $factor['label'] }}</li>@endforeach</ul>
            </section>
        @endif
    @endif

    @if ($customer !== [])
        <section class="section">
            <h2>اطلاعات مشتری</h2>
            <table class="customer-table">
                @foreach ($customer as $label => $value)
                    <tr>
                        <td class="report-table__label">{{ $label }}</td>
                        <td class="report-table__value"><bdi dir="auto">{{ $value }}</bdi></td>
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
                        <td class="report-table__label">{{ $input['label'] }}</td>
                        <td class="report-table__value"><bdi dir="auto">{{ $input['value'] }}</bdi></td>
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
                            <td class="report-table__label">{{ $output['label'] }}</td>
                            <td class="report-table__value"><bdi dir="auto">{{ $output['value'] }}</bdi></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </section>
    @endif

    @if ($eligibleScores !== [])
        <section class="section">
            <h2>{{ $excludedScores === [] ? 'خلاصه امتیازها' : 'رتبه‌بندی گزینه‌های واجد شرایط' }}</h2>
            <table class="scores-table">
                @foreach ($eligibleScores as $score)
                    <tr @class(['recommended' => $score['recommended']])>
                        <td class="report-table__label">
                            @if ($score['rank'] !== null)رتبه {{ $weighted ? \App\Support\PersianDate::digits($score['rank']) : $score['rank'] }} — @endif<bdi dir="auto">{{ $score['label'] }}</bdi>
                        </td>
                        <td class="report-table__value"><bdi dir="auto">{{ $score['value'] }}</bdi></td>
                    </tr>
                @endforeach
            </table>
        </section>
    @endif

    @if ($excludedScores !== [])
        <section class="section">
            <h2>گزینه‌های خارج‌شده</h2>
            <table class="scores-table">
                @foreach ($excludedScores as $score)
                    <tr>
                        <td class="report-table__label"><bdi dir="auto">{{ $score['label'] }}</bdi> — خارج از شرایط</td>
                        <td class="report-table__value"><bdi dir="auto">{{ $score['value'] }}</bdi></td>
                    </tr>
                    @foreach ($score['reasons'] as $reason)
                        <tr>
                            <td colspan="2">علت: {{ $reason }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </table>
        </section>
    @endif

    @if (filled($resultDescription ?? null) || $benefits !== [])
        <section class="section">
            <h2>مزایا و توضیحات نتیجه</h2>
            @if (filled($resultDescription ?? null))<p class="result-content">{{ $resultDescription }}</p>@endif
            <ul>
                @foreach ($benefits as $benefit)<li>{{ $benefit }}</li>@endforeach
            </ul>
        </section>
    @endif

    @if (filled($resultNote ?? null))
        <section class="section">
            <h2>نکته پایانی</h2>
            <p class="result-content">{{ $resultNote }}</p>
        </section>
    @endif

    <footer class="footer">
        <div>تاریخ تولید گزارش: {{ \App\Support\PersianDate::dateTime($generatedAt) }}</div>
        <div class="footer-contact">
            راه‌های ارتباطی:
            <bdi dir="auto">{{ $brand['phone'] ?: 'شماره تماس مجموعه' }}</bdi>
            @if ($brand['email']) | <bdi dir="ltr">{{ $brand['email'] }}</bdi> @endif
            @if ($brand['address']) | {{ $brand['address'] }} @endif
        </div>
    </footer>
</body>
</html>
