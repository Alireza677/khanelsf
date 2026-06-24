<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <x-slot name="label">
        {{ $getLabel() }}
    </x-slot>

    <div
        x-data="{
            primaryColor: $wire.entangle('data.primary_color').live,
            secondaryColor: $wire.entangle('data.secondary_color').live,
            accentColor: $wire.entangle('data.accent_color').live,
            textColor: $wire.entangle('data.text_color').live,
            backgroundColor: $wire.entangle('data.background_color').live,
            fontFamilyValue: $wire.entangle('data.font_family').live,
            customFontName: $wire.entangle('data.custom_font_name').live,
            sizeDevice: $wire.entangle('data.theme_size_device').live,
            baseFontSize: $wire.entangle('data.base_font_size').live,
            baseFontSizeMobile: $wire.entangle('data.base_font_size_mobile').live,
            buttonFontSize: $wire.entangle('data.button_font_size').live,
            buttonFontSizeMobile: $wire.entangle('data.button_font_size_mobile').live,
            h1FontSize: $wire.entangle('data.h1_font_size').live,
            h1FontSizeMobile: $wire.entangle('data.h1_font_size_mobile').live,
            h2FontSize: $wire.entangle('data.h2_font_size').live,
            h2FontSizeMobile: $wire.entangle('data.h2_font_size_mobile').live,
            h3FontSize: $wire.entangle('data.h3_font_size').live,
            h3FontSizeMobile: $wire.entangle('data.h3_font_size_mobile').live,
            h4FontSize: $wire.entangle('data.h4_font_size').live,
            h4FontSizeMobile: $wire.entangle('data.h4_font_size_mobile').live,
            buttonRadius: $wire.entangle('data.button_radius').live,
            buttonRadiusMobile: $wire.entangle('data.button_radius_mobile').live,
            value(value, fallback) {
                return value && String(value).trim() ? value : fallback
            },
            size(desktop, mobile, fallback) {
                return this.sizeDevice === 'mobile'
                    ? this.value(mobile, this.value(desktop, fallback))
                    : this.value(desktop, fallback)
            },
            fontFamily() {
                if (this.fontFamilyValue === 'serif') {
                    return 'Georgia, Cambria, Times New Roman, Times, serif'
                }

                if (this.fontFamilyValue === 'mono') {
                    return 'Consolas, Liberation Mono, monospace'
                }

                if (this.fontFamilyValue === 'custom') {
                    return `'${this.value(this.customFontName, 'فونت سفارشی سایت')}', system-ui, sans-serif`
                }

                return 'system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif'
            },
        }"
        class="sticky top-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">نمای زنده تنظیمات قالب</p>
        </div>

        <div
            class="space-y-4 p-5 transition-all"
            x-bind:class="sizeDevice === 'mobile' ? 'mx-auto max-w-[360px]' : 'max-w-none'"
            x-bind:style="{
                backgroundColor: value(backgroundColor, '#f8fafc'),
                color: value(textColor, '#1f2937'),
                fontFamily: fontFamily(),
            }"
        >
            <div class="space-y-2">
                <h1
                    class="m-0 font-black leading-tight"
                    x-bind:style="{ color: value(secondaryColor, '#111827'), fontSize: size(h1FontSize, h1FontSizeMobile, '2.5rem') }"
                >
                    عنوان اصلی H1
                </h1>
                <h2
                    class="m-0 font-extrabold leading-tight"
                    x-bind:style="{ color: value(secondaryColor, '#111827'), fontSize: size(h2FontSize, h2FontSizeMobile, '2rem') }"
                >
                    عنوان بخش H2
                </h2>
                <h3
                    class="m-0 font-bold leading-snug"
                    x-bind:style="{ color: value(secondaryColor, '#111827'), fontSize: size(h3FontSize, h3FontSizeMobile, '1.25rem') }"
                >
                    زیرعنوان H3
                </h3>
                <h4
                    class="m-0 font-bold leading-snug"
                    x-bind:style="{ color: value(secondaryColor, '#111827'), fontSize: size(h4FontSize, h4FontSizeMobile, '1.125rem') }"
                >
                    تیتر کوچک H4
                </h4>
            </div>

            <p
                class="m-0 leading-8"
                x-bind:style="{ color: value(textColor, '#1f2937'), fontSize: size(baseFontSize, baseFontSizeMobile, '16px') }"
            >
                این متن نمونه برای بررسی اندازه متن‌های عمومی، رنگ متن و خانواده فونت سایت است.
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    href="#"
                    class="inline-flex min-h-11 items-center justify-center px-4 font-bold text-white no-underline"
                    x-bind:style="{
                        backgroundColor: value(primaryColor, '#2563eb'),
                        borderRadius: size(buttonRadius, buttonRadiusMobile, '6px'),
                        fontSize: size(buttonFontSize, buttonFontSizeMobile, '1rem'),
                    }"
                >
                    دکمه اصلی
                </a>
                <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
                    x-bind:style="{ backgroundColor: value(accentColor, '#0f766e'), color: '#fff' }"
                >
                    رنگ تاکیدی
                </span>
            </div>
        </div>
    </div>
</x-dynamic-component>
