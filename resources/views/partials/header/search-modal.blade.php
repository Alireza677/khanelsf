<section
    class="header-overlay header-search-modal"
    id="{{ $overlayId }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $overlayId }}-title"
    data-header-overlay
    hidden
>
    <button class="header-overlay__backdrop" type="button" aria-label="بستن جستجو" data-header-overlay-close></button>
    <div class="header-search-modal__panel" data-header-overlay-panel tabindex="-1">
        <button class="header-overlay__close" type="button" aria-label="بستن جستجو" data-header-overlay-close>×</button>
        <h2 id="{{ $overlayId }}-title">دنبال چه چیزی می‌گردید؟</h2>
        <p>در بخش‌های مختلف سایت جستجو کنید.</p>
        <form method="get" action="{{ $searchUrl }}" data-search-scope>
            <label for="{{ $overlayId }}-query">عبارت موردنظر</label>
            <input
                id="{{ $overlayId }}-query"
                name="q"
                type="search"
                placeholder="عبارت موردنظر را جستجو کنید..."
                minlength="2"
                maxlength="100"
                required
                data-header-overlay-autofocus
            >

            <div class="search-scope-selector" id="{{ $overlayId }}-scope" data-search-scope-selector>
                <div class="search-scope-selector__summary" aria-live="polite" data-search-scope-summary>جستجو در همه</div>
                <div class="search-scope-selector__options">
                    <label class="search-scope-option search-scope-option--all">
                        <input type="checkbox" checked data-search-scope-all>
                        <span>همه</span>
                    </label>
                    @foreach ($searchSources as $value => $label)
                        <label class="search-scope-option">
                            <input type="checkbox" value="{{ $value }}" checked data-search-scope-type>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <button
                    class="search-scope-selector__toggle"
                    type="button"
                    aria-expanded="false"
                    aria-controls="{{ $overlayId }}-scope"
                    aria-label="نمایش محدوده‌های جستجو"
                    data-search-scope-toggle
                >
                    <i class="icon-arrow-circle-left fhai search-scope-selector__icon search-scope-selector__icon--closed" aria-hidden="true"></i>
                    <i class="icon-arrow-circle-right search-scope-selector__icon search-scope-selector__icon--expanded" aria-hidden="true"></i>
                </button>
            </div>

            <button class="button" type="submit">جستجو</button>
        </form>
    </div>
</section>
