@php
    $serviceName = \App\Models\Service::find($data['service_id'] ?? null)?->name;
@endphp
<div class="space-y-5 text-sm" dir="rtl">
    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div><dt class="font-medium">خدمت</dt><dd>{{ $serviceName ?: 'انتخاب نشده' }}</dd></div>
        <div><dt class="font-medium">بسته</dt><dd>{{ $data['name'] ?? 'نام تعیین نشده' }}</dd></div>
        <div><dt class="font-medium">نحوه ارائه</dt><dd>{{ \App\Enums\ClientProjectDeliveryMode::options()[$data['delivery_mode'] ?? ''] ?? 'تعیین نشده' }}</dd></div>
        <div><dt class="font-medium">زمان‌بندی</dt><dd>{{ \App\Filament\Resources\ClientProjectResource::scheduleModeOptions()[$data['schedule_mode'] ?? ''] ?? 'تعیین نشده' }}</dd></div>
        <div><dt class="font-medium">قیمت</dt><dd>{{ filled($data['amount'] ?? null) ? $data['amount'].' '.($data['currency_code'] ?? '') : 'نیازمند تعیین مبلغ' }}</dd></div>
        <div><dt class="font-medium">مبنای مبلغ و دوره</dt><dd>{{ \App\Services\ServiceAgreementData::BILLING_BASES[$data['billing_basis'] ?? ''] ?? '—' }} / {{ \App\Services\ServiceAgreementData::BILLING_INTERVALS[$data['billing_interval'] ?? ''] ?? '—' }}</dd></div>
    </dl>
    <div>
        <h3 class="font-medium">تعهدات تحویل</h3>
        <ul class="mt-2 space-y-2">
            @forelse ($data['deliverables'] ?? [] as $line)
                <li>{{ $line['name'] ?? 'تعهد بدون نام' }} — {{ \App\Support\ServiceDeliveryNumber::format($line['quantity'] ?? null) ?? '—' }} {{ $line['unit'] ?? '' }}؛ {{ !empty($line['approval_required']) ? 'نیازمند تأیید مشتری' : 'بدون تأیید مشتری' }}؛ اصلاح مجاز: {{ $line['revision_allowance'] ?? 0 }}
                    @if (filled(data_get($line, 'scheduling_rules.due_day_offset')))
                        ؛ موعد: {{ data_get($line, 'scheduling_rules.due_day_offset') }} روز پس از شروع دوره
                    @endif
                </li>
            @empty
                <li>تعهدی اضافه نشده؛ برای انتشار بسته وظیفه‌محور یا ترکیبی، تعهد اضافه کنید.</li>
            @endforelse
        </ul>
    </div>
    <div>
        <h3 class="font-medium">ویژگی‌ها و شرایط بسته</h3>
        <ul class="mt-2 space-y-2">
            @forelse ($data['features'] ?? [] as $feature)
                <li>{{ $feature['title'] ?? 'ویژگی بدون عنوان' }}: {{ match ($feature['type'] ?? null) { 'boolean' => !empty($feature['value']) ? 'دارد' : 'ندارد', 'number' => \App\Support\ServiceDeliveryNumber::format($feature['value'] ?? null) ?? '—', default => $feature['value'] ?? '—' } }} {{ ($feature['type'] ?? null) === 'number' ? ($feature['unit'] ?? '') : '' }}</li>
            @empty
                <li>ویژگی اختیاری تعریف نشده است.</li>
            @endforelse
        </ul>
    </div>
    <div><h3 class="font-medium">شرایط قرارداد</h3><p class="mt-2 whitespace-pre-wrap">{{ $data['contract_terms'] ?: 'شرطی ثبت نشده است.' }}</p></div>
    <p class="text-gray-500 dark:text-gray-400">پیش‌نویس قابل ویرایش است. پس از انتشار، قیمت، تعهدات و ویژگی‌های این نسخه قفل می‌شوند؛ تغییر بعدی در نسخه جدید انجام می‌شود.</p>
</div>
