<div dir="rtl" class="space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
    @if ($operation !== 'delete')
        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem">
            @foreach (['تصویر فعلی' => $source, 'تصویر جایگزین' => $replacement] as $label => $image)
                <div class="min-w-0">
                    <p class="text-sm">{{ $label }}</p>
                    @if ($image)
                        <img src="{{ $image->getUrl() }}" alt="{{ $image->displayTitle() }}" style="width: 100%; max-height: 9rem; object-fit: contain">
                        <p class="text-xs" style="overflow-wrap: anywhere">{{ $image->displayTitle() }}</p>
                    @else
                        <p class="text-sm text-gray-500">هنوز انتخاب نشده است.</p>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="text-sm">جایگزینی در {{ count($selected) }} محل انتخاب‌شده:</p>
        <ul class="text-sm" style="overflow-wrap: anywhere">
            @foreach ($selected as $row)
                <li>{{ $row['title'] }} — {{ $row['label'] }}</li>
            @endforeach
        </ul>
        @if (collect($selected)->contains('shared_template', true))
            <p class="text-sm">انتخاب شما شامل قالب مشترک است؛ همهٔ محتواهای استفاده‌کننده از آن، تصویر جدید را نمایش می‌دهند.</p>
        @endif
    @endif
    @if (($remaining ?? 0) > 0)
        <p class="text-sm">{{ $remaining }} محل هنوز انتخاب نشده یا نیازمند اصلاح دستی است؛ حذف تصویر قبلی فعال نمی‌شود.</p>
    @endif
    <p class="text-sm">{{ $operation === 'replace' ? 'تصویر قبلی حذف نمی‌شود.' : 'پس از بررسی نهایی نبود وابستگی، رکورد و فایل تصویر قبلی حذف می‌شوند.' }}</p>
</div>
