@php
    $presenter = app(\App\Services\FormSubmissionPresenter::class);
    $submitter = app(\App\Services\FormSubmissionSubmitterResolver::class);
    $answers = $presenter->answers($submission);
    $source = match ($submission->source) {
        'website' => 'وب‌سایت',
        'manual' => 'دستی',
        default => $submission->source ?: '—',
    };
@endphp
یک ورودی جدید ثبت شد.

فرم: {{ $submission->form?->name ?? '—' }}
تاریخ ارسال: {{ $submission->submitted_at?->format('Y-m-d H:i') ?? '—' }}
منبع: {{ $source }}
ارسال‌کننده: {{ $submitter->resolve($submission) }}
ایمیل: {{ $submitter->email($submission) ?? '—' }}
تلفن: {{ $submitter->phone($submission) ?? '—' }}

@if ($answers !== [])
پاسخ‌ها:
@foreach ($answers as $answer)
- {{ $answer['label'] }}: {{ $answer['value'] }}
@endforeach
@endif
