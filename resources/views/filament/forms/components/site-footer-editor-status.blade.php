<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        dir="rtl"
        class="space-y-3 text-sm"
        x-data="{
            focusField(path) {
                activeInspectorTab = path.includes('.settings.') && !path.includes('.columns.') ? 'design' : 'content'
                this.$nextTick(() => {
                    const field = document.getElementById(path)
                    if (!field) return
                    let parent = field.parentElement
                    while (parent) {
                        if (parent.matches('.fi-fo-repeater-item')) parent.dispatchEvent(new CustomEvent('expand'))
                        parent = parent.parentElement
                    }
                    this.$nextTick(() => { field.focus(); field.scrollIntoView({ block: 'center', behavior: 'auto' }) })
                })
            },
            focusElement(id) {
                const scope = this.$root.closest('.block-builder-inspector__selection') || this.$root
                const element = Array.from(scope.querySelectorAll('[data-footer-element-id]')).find(node => node.dataset.footerElementId === id)
                if (!element) return
                activeInspectorTab = 'content'
                let parent = element.parentElement
                while (parent) {
                    if (parent.matches('.fi-fo-repeater-item')) parent.dispatchEvent(new CustomEvent('expand'))
                    parent = parent.parentElement
                }
                this.$nextTick(() => { element.focus(); element.scrollIntoView({ block: 'center', behavior: 'auto' }) })
            },
        }"
        x-on:footer-element-moved.window="if ($event.detail.footerPath === @js($footerStatePath)) setTimeout(() => focusElement($event.detail.id), 200)"
    >
        <p>پیش‌نمایش، آخرین نسخه ذخیره‌شده را با Runtime اصلی نمایش می‌دهد. برای دیدن تغییرات، ابتدا فرم را ذخیره کنید.</p>
        <p wire:dirty role="status" class="font-medium text-warning-600 dark:text-warning-400">فرم تغییرات ذخیره‌نشده دارد؛ این تغییرات هنوز در پیش‌نمایش و سایت اعمال نشده‌اند.</p>

        @if ($footerErrors !== [])
            <div
                wire:key="footer-errors-{{ md5($footerStatePath . json_encode($footerErrors)) }}"
                tabindex="-1"
                role="alert"
                aria-label="خطاهای فوتر"
                x-init="$nextTick(() => $el.focus())"
                class="space-y-2 text-danger-600 dark:text-danger-400"
            >
                <strong>فوتر ذخیره نشد؛ موارد زیر را اصلاح کنید:</strong>
                <ul class="list-inside list-disc">
                    @foreach ($footerErrors as $path => $messages)
                        @foreach ($messages as $message)
                            <li>
                                <button
                                    type="button"
                                    class="text-start underline"
                                    x-on:click="focusField(@js(str_starts_with($path, $footerStatePath . '.') ? $path : $footerStatePath . '.' . $path))"
                                >{{ $message }}</button>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-dynamic-component>
