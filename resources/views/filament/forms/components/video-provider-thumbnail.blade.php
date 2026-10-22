<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($thumbnailUrl)
        <img src="{{ $thumbnailUrl }}" alt="تصویر خودکار ویدئو" class="h-24 w-auto rounded-lg" loading="lazy" referrerpolicy="no-referrer">
    @else
        <p class="text-sm text-gray-500">تصویر خودکار موجود نیست؛ می‌توانید تصویر جایگزین انتخاب کنید.</p>
    @endif
</x-dynamic-component>
