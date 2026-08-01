@php
    $recommendations = data_get($calculationResult, 'score_labels', data_get($form->schema, 'calculator.recommendations', []));
    $recommendations = is_array($recommendations) ? $recommendations : [];
    $scores = data_get($calculationResult, 'scores', []);
    $scores = is_array($scores) ? $scores : [];
    $recommendedMethod = data_get($calculationResult, 'recommended_method');
    $resultLabel = data_get($calculationResult, 'result');
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
    $scoreMaximum = collect($scores)->filter(fn (mixed $score): bool => is_numeric($score))->map(fn ($score): float => max(0, (float) $score))->max() ?: 0;
    $settings = app(\App\Services\SettingsService::class);
    $contactPhone = $settings->contactPhone();
    $ctaUrl = filled($contactPhone)
        ? 'tel:'.preg_replace('/[^\d+]/', '', $contactPhone)
        : ($settings->headerCtaUrl() ?: route('contact.create', absolute: false));
    $ctaLabel = filled($contactPhone) ? 'تماس با مشاور' : ($settings->headerCtaLabel() ?: 'درخواست مشاوره');
@endphp

<div
    class="calculator-result-modal"
    data-calculator-result-modal
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $modalId }}-title"
    aria-describedby="{{ $modalId }}-intro"
    hidden
>
    <article class="calculator-result-modal__panel calculator-result-card">
        <button
            class="calculator-result-modal__close"
            type="button"
            data-calculator-result-close
            aria-label="بستن نتیجه"
        >&times;</button>

        <header class="calculator-result-card__header">
            <span class="calculator-result-card__eyebrow">گزارش ارزیابی</span>
            <h2 id="{{ $modalId }}-title">نتیجه ارزیابی شما</h2>
            <p id="{{ $modalId }}-intro">این نتیجه بر اساس پاسخ‌هایی که ثبت کرده‌اید تهیه شده است.</p>
        </header>

        <section class="calculator-result-card__hero" aria-label="پیشنهاد اصلی">
            <span>پیشنهاد مناسب برای شما</span>
            <strong>{{ $resultLabel }}</strong>
            @if (filled($resultReason))
                <p>{{ $resultReason }}</p>
            @endif
        </section>

        @if ($answerLabels !== [])
            <section class="calculator-result-card__section">
                <h3>اطلاعات وارد شده توسط کاربر</h3>
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

        @if ($scores !== [])
            <section class="calculator-result-card__section calculator-result-card__scores">
                <h3>مقایسه نتایج</h3>
                <div class="calculator-result-card__score-list">
                    @foreach ($scores as $key => $score)
                        @php
                            $scoreLabel = $recommendations[$key]
                                ?? ($key === $recommendedMethod ? $resultLabel : 'نتیجه '.$loop->iteration);
                            $scoreWidth = $scoreMaximum > 0 && is_numeric($score)
                                ? max(0, min(100, ((float) $score / $scoreMaximum) * 100))
                                : 0;
                        @endphp
                        <div @class(['is-recommended' => $key === $recommendedMethod])>
                            <div class="calculator-result-card__score-heading">
                                <span>{{ $scoreLabel }}</span>
                                <strong>{{ $score }}</strong>
                            </div>
                            <span class="calculator-result-card__score-track" aria-hidden="true">
                                <span style="width: {{ $scoreWidth }}%"></span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (filled($resultDescription) || $benefits !== [])
            <section class="calculator-result-card__section">
                <h3>مزایا یا توضیحات نتیجه</h3>
                @if (filled($resultDescription))
                    <p>{{ $resultDescription }}</p>
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
