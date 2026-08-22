<dl class="grid gap-4 text-sm sm:grid-cols-2">
    <div><dt class="text-gray-500">شناسه</dt><dd class="font-mono">{{ $backup->uuid }}</dd></div>
    <div><dt class="text-gray-500">نوع</dt><dd>{{ $backup->type->label() }}</dd></div>
    <div><dt class="text-gray-500">منبع</dt><dd>{{ $backup->source->label() }}</dd></div>
    <div><dt class="text-gray-500">وضعیت</dt><dd>{{ $backup->status->label() }}</dd></div>
    <div><dt class="text-gray-500">درخواست</dt><dd>{{ \App\Support\PersianDate::dateTime($backup->created_at) }}</dd></div>
    <div><dt class="text-gray-500">شروع</dt><dd>{{ $backup->started_at ? \App\Support\PersianDate::dateTime($backup->started_at) : '—' }}</dd></div>
    <div><dt class="text-gray-500">پایان</dt><dd>{{ $backup->finished_at ? \App\Support\PersianDate::dateTime($backup->finished_at) : '—' }}</dd></div>
    <div><dt class="text-gray-500">حجم</dt><dd>{{ $backup->size_bytes ? number_format($backup->size_bytes).' بایت' : '—' }}</dd></div>
    <div><dt class="text-gray-500">محل نگهداری</dt><dd>فضای خصوصی سرور</dd></div>
    <div><dt class="text-gray-500">Checksum</dt><dd class="break-all font-mono text-xs">{{ $backup->checksum ?: '—' }}</dd></div>
    <div><dt class="text-gray-500">ایجادکننده</dt><dd>{{ $backup->requestedBy?->name ?: 'سیستم' }}</dd></div>
    <div><dt class="text-gray-500">تعداد تلاش</dt><dd>{{ $backup->attempt }}</dd></div>
    @if ($backup->status === \App\Enums\BackupStatus::Failed)<div class="sm:col-span-2"><dt class="text-gray-500">خطا</dt><dd class="text-danger-600">{{ $backup->safeFailureMessage() }}</dd></div>@endif
</dl>
