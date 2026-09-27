@php
    $scoreRows = app(\App\Services\Calculators\CalculationResultRows::class)->fromSnapshot(
        is_array($calculationResult) ? $calculationResult : [],
    );
    $weighted = app(\App\Services\Calculators\CalculationResultRows::class)->weightedSummary(is_array($calculationResult) ? $calculationResult : []);
    $eligibleScoreRows = array_values(array_filter($scoreRows, fn (array $row): bool => $row['eligible'] !== false));
    $excludedScoreRows = array_values(array_filter($scoreRows, fn (array $row): bool => $row['eligible'] === false));
    $noEligibleRecommendation = data_get($calculationResult, 'no_eligible_recommendation') === true;
    $resultLabel = data_get($calculationResult, 'result_title') ?? data_get($calculationResult, 'result');
    $resultSummary = data_get($calculationResult, 'result_summary');
    $resultNote = data_get($calculationResult, 'result_note');
    $answerLabels = data_get($calculationResult, 'answer_labels', []);
    $answerLabels = is_array($answerLabels) ? $answerLabels : [];
    $fieldLabels = collect(app(\App\Services\FormSchema::class)->fields($form))
        ->filter(fn (array $field): bool => ! in_array($field['type'], ['page', 'step'], true))
        ->mapWithKeys(fn (array $field): array => [$field['name'] => $field['label']])
        ->merge(is_array(data_get($calculationResult, 'answer_field_labels')) ? data_get($calculationResult, 'answer_field_labels') : []);
    $resultReason = data_get($calculationResult, 'reason') ?? data_get($calculationResult, 'explanation');
    $projectSummary = data_get($calculationResult, 'project_summary') ?? data_get($calculationResult, 'summary');
    $resultDescription = data_get($calculationResult, 'result_description') ?? data_get($calculationResult, 'description');
    $benefits = data_get($calculationResult, 'benefits', []);
    $benefits = is_array($benefits) ? array_values(array_filter($benefits, 'is_scalar')) : [];
    $outputs = data_get($calculationResult, 'outputs', []);
    $outputs = is_array($outputs)
        ? array_values(array_filter($outputs, fn (mixed $output): bool => is_array($output) && filled($output['label'] ?? null) && filled($output['value'] ?? null)))
        : [];
    $settings = app(\App\Services\SettingsService::class);
    $contactPhone = $settings->contactPhone();
    $ctaUrl = filled($contactPhone)
        ? 'tel:'.preg_replace('/[^\d+]/', '', $contactPhone)
        : ($settings->headerCtaUrl() ?: route('contact.create', absolute: false));
    $ctaLabel = filled($contactPhone) ? 'تماس با مشاور' : ($settings->headerCtaLabel() ?: 'درخواست مشاوره');
@endphp

<div
    id="{{ $modalId }}"
    class="calculator-result-modal"
    data-calculator-result-modal
    role="dialog"
    aria-modal="true"
    hidden
    aria-labelledby="{{ $modalId }}-title"
    aria-describedby="{{ $modalId }}-intro"
    @if ($weighted) dir="rtl" @endif
>
    <article class="calculator-result-card calculator-result-modal__panel">
        <button
            class="calculator-result-modal__close"
            type="button"
            data-calculator-result-close
            aria-label="بستن نتیجه"
        >&times;</button>

        <header class="calculator-result-card__header">
            <span class="calculator-result-card__eyebrow">گزارش ارزیابی</span>
            <h2 id="{{ $modalId }}-title">نتیجه ارزیابی شما</h2>
            <p id="{{ $modalId }}-intro">{{ $weighted ? 'این ارزیابی بر اساس پاسخ‌های شما و معیارها، وزن‌ها و امتیازهای تعریف‌شده در این فرم تهیه شده است.' : 'این نتیجه بر اساس پاسخ‌هایی که ثبت کرده‌اید تهیه شده است.' }}</p>
        </header>

        <div class="calculator-result-modal__body" role="region" aria-label="جزئیات نتیجه ارزیابی" tabindex="0">
        <section class="calculator-result-card__hero" aria-label="پیشنهاد اصلی">
            @if ($noEligibleRecommendation)
                <span>نیازمند بررسی کارشناسی</span>
                <strong>هیچ گزینه واجد شرایطی یافت نشد</strong>
                <p>بر اساس پاسخ‌های واردشده، هیچ‌یک از گزینه‌های تعریف‌شده شرایط لازم را ندارند.</p>
            @elseif ($weighted && $weighted['no_score'])
                <span>امتیازی برای مقایسه محاسبه نشد</span>
                <strong>وزن معیارهای تصمیم‌گیری صفر است</strong>
                <p>با پاسخ‌های فعلی، امکان تعیین میزان تطابق و پیشنهاد اصلی وجود ندارد.</p>
            @else
                <span>{{ $weighted ? 'پیشنهاد اصلی بر اساس پاسخ‌های شما' : 'پیشنهاد مناسب برای شما' }}</span>
                <strong>{{ $resultLabel }}</strong>
                @if (filled($resultSummary))
                    <p class="whitespace-pre-line">{{ $resultSummary }}</p>
                @endif
                @if ($weighted && $weighted['suitability_label'] !== null)
                    <p>میزان تطابق: {{ $weighted['suitability_label'] }}</p>
                @endif
            @endif
            @if (! $noEligibleRecommendation && filled($resultReason))
                <p>{{ $resultReason }}</p>
            @endif
        </section>

        @if ($decisionReport = $weighted['decision_report'] ?? null)
            <section class="calculator-result-card__section calculator-decision-report" dir="rtl">
                <h3>چرا این پیشنهاد برای شما مناسب‌تر است؟</h3>
                <p>{{ $decisionReport['intro'] }}</p>
                @foreach ($decisionReport['factors'] as $factor)
                    <div class="calculator-decision-report__factor">
                        <h4>{{ $factor['label'] }}</h4>
                        <p>{{ $factor['explanation'] }}</p>
                    </div>
                @endforeach
                <p class="calculator-decision-report__summary">{{ $decisionReport['summary'] }}</p>
            </section>
        @elseif ($weighted && $weighted['factors'] !== [])
            <section class="calculator-result-card__section">
                <h3>مهم‌ترین عوامل مؤثر در این نتیجه</h3>
                <ul class="calculator-result-card__benefits">
                    @foreach ($weighted['factors'] as $factor)
                        <li>{{ $factor['label'] }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($answerLabels !== [])
            <section class="calculator-result-card__section">
                <h3>اطلاعات وارد شده توسط شما</h3>
                <dl class="calculator-result-card__details">
                    @foreach ($answerLabels as $key => $answerLabel)
                        <div>
                            <dt>{{ $fieldLabels[$key] ?? 'پاسخ '.$loop->iteration }}</dt>
                            <dd>{{ $answerLabel }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if (filled($projectSummary) || $outputs !== [])
            <section class="calculator-result-card__section">
                <h3>خلاصه پروژه</h3>
                @if (filled($projectSummary))
                    <p>{{ $projectSummary }}</p>
                @endif
                @if ($outputs !== [])
                    <dl class="calculator-result-card__details">
                        @foreach ($outputs as $output)
                            <div>
                                <dt>{{ $output['label'] }}</dt>
                                <dd>{{ $output['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endif

        @if ($eligibleScoreRows !== [])
            <section class="calculator-result-card__section calculator-result-card__scores">
                <h3>{{ $excludedScoreRows === [] ? 'مقایسه نتایج' : 'رتبه‌بندی گزینه‌های واجد شرایط' }}</h3>
                @if (collect($eligibleScoreRows)->contains(fn (array $row): bool => $row['rank'] !== null))
                    <p>رتبه‌بندی بر اساس امتیاز تطابق محاسباتی</p>
                @endif
                <div class="calculator-result-card__score-list">
                    @foreach ($eligibleScoreRows as $scoreRow)
                        <div @class(['is-recommended' => $scoreRow['recommended']])>
                            <div class="calculator-result-card__score-heading">
                                <span>
                                    @if ($scoreRow['rank'] !== null)
                                        رتبه {{ $weighted ? \App\Support\PersianDate::digits($scoreRow['rank']) : $scoreRow['rank'] }} —
                                    @endif
                                    {{ $scoreRow['label'] }}
                                    @if ($scoreRow['recommended'])
                                        — پیشنهاد نهایی
                                    @endif
                                </span>
                                @if ($weighted)
                                    <strong>میزان تطابق: {{ $scoreRow['suitability_label'] }}</strong>
                                @else
                                    <strong>امتیاز {{ $scoreRow['score'] }}</strong>
                                @endif
                            </div>
                            @if ($weighted)<p>امتیاز خام: {{ $scoreRow['raw_score_label'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($excludedScoreRows !== [])
            <section class="calculator-result-card__section calculator-result-card__scores">
                <h3>گزینه‌های خارج‌شده</h3>
                <div class="calculator-result-card__score-list">
                    @foreach ($excludedScoreRows as $scoreRow)
                        <div>
                            <div class="calculator-result-card__score-heading">
                                <span>{{ $scoreRow['label'] }} — خارج از شرایط</span>
                                @if ($weighted)
                                    <strong>میزان تطابق: {{ $scoreRow['suitability_label'] }}</strong>
                                @else
                                    <strong>امتیاز {{ $scoreRow['score'] }}</strong>
                                @endif
                            </div>
                            @if ($scoreRow['reasons'] !== [])
                                <ul class="calculator-result-card__benefits">
                                    @foreach ($scoreRow['reasons'] as $reason)
                                        <li>{{ $reason['message'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (filled($resultDescription) || $benefits !== [])
            <section class="calculator-result-card__section">
                <h3>مزایا یا توضیحات نتیجه</h3>
                @if (filled($resultDescription))
                    <p class="whitespace-pre-line">{{ $resultDescription }}</p>
                @endif
                @if ($benefits !== [])
                    <ul class="calculator-result-card__benefits">
                        @foreach ($benefits as $benefit)
                            <li>{{ $benefit }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if (filled($resultNote))
            <section class="calculator-result-card__section">
                <h3>نکته پایانی</h3>
                <p class="whitespace-pre-line">{{ $resultNote }}</p>
            </section>
        @endif
        </div>

        <footer class="calculator-result-card__cta">
            <div>
                <strong>گام بعدی را با اطمینان بردارید</strong>
                <p>برای دریافت مشاوره تخصصی با ما تماس بگیرید.</p>
            </div>
            <div class="calculator-result-card__actions">
                @if (filled($reportUrl ?? null))
                    <a class="button calculator-result-card__report-button" href="{{ $reportUrl }}">دریافت گزارش PDF</a>
                @endif
                <a class="button" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>
            </div>
        </footer>
    </article>
</div>
