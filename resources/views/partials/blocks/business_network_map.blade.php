@if($network)
@php($id = 'business-network-'.($network['block']['block_id'] ?: uniqid()))
<section id="{{ $id }}" class="business-network" dir="rtl" data-business-network data-initial-province="{{ $network['selected_province'] }}">
    @if($network['block']['content']['title'])<h2 class="bn-title">{{ $network['block']['content']['title'] }}</h2>@endif
    @if($network['block']['content']['description'])<div class="bn-description">{!! nl2br(e($network['block']['content']['description'])) !!}</div>@endif

    @if($network['settings']['show_search'])
        <label class="bn-search"><span class="sr-only">جستجو</span><input data-network-search type="search" placeholder="جستجو در نام، شهر، مسئول یا استان"></label>
    @endif

    <div class="bn-explorer">
        @if($network['settings']['show_map'])
            <div class="bn-map-panel"><div class="business-network-panel__title">نقشه ایران</div><div class="bn-map-shell">@include('partials.maps.iran-provinces')</div></div>
        @endif
        @if($network['settings']['show_province_list'])
            <div class="bn-province-panel">
                <div class="bn-panel-heading"><div class="business-network-provinces__title">استان‌ها</div><button type="button" class="bn-clear-provinces" data-clear-provinces>پاک کردن انتخاب</button></div>
                <div class="bn-provinces" aria-label="انتخاب استان‌ها" aria-multiselectable="true">
                    @foreach($network['provinces'] as $province)
                        <button type="button" data-province-code="{{ $province['province_code'] }}" aria-pressed="false">
                            <span>{{ $province['province_name'] }}</span><b>{{ $province['active_count'] }}</b>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="bn-summary-row">
        <p class="bn-summary" data-network-summary aria-live="polite"></p>
        <button type="button" class="bn-type-filter-trigger" data-type-filter-open aria-haspopup="dialog" aria-controls="{{ $id }}-type-dialog">
            <span>فیلتر نوع</span><span class="bn-filter-badge" data-type-filter-badge hidden></span>
        </button>
    </div>

    <div class="bn-cards" data-network-cards style="--bn-columns:{{ $network['settings']['card_columns'] }}">
        @foreach($network['provinces'] as $province)
            @foreach($province['locations'] as $item)
                <article class="bn-card" data-province="{{ $province['province_code'] }}" data-type="{{ $item['type'] }}" data-search-text="{{ Illuminate\Support\Str::lower(implode(' ', array_filter([$item['province_name'],$item['city'],$item['name'],$item['contact_name']]))) }}" hidden>
                    <div class="business-network-card__title">{{ $item['name'] }}</div>
                    @if($network['settings']['show_type'])<p>{{ $item['type_label'] }}</p>@endif
                    <p>{{ $item['city'] }}</p>
                    @if($network['settings']['show_contact_name'] && $item['contact_name'])<p>{{ $item['contact_name'] }}</p>@endif
                    @if($network['settings']['show_position'] && $item['position'])<p>{{ $item['position'] }}</p>@endif
                    @if($network['settings']['show_mobile'] && $item['mobile'])<a href="tel:{{ $item['mobile'] }}">{{ $item['mobile'] }}</a>@endif
                    @if($network['settings']['show_phone'] && $item['phone'])<a href="tel:{{ $item['phone'] }}">{{ $item['phone'] }}</a>@endif
                    @if($network['settings']['show_email'] && $item['email'])<a href="mailto:{{ $item['email'] }}">{{ $item['email'] }}</a>@endif
                    @if($network['settings']['show_address'] && $item['address'])<address>{{ $item['address'] }}</address>@endif
                </article>
            @endforeach
        @endforeach
    </div>

    <div class="bn-empty" data-network-empty hidden>
        <div class="business-network-empty__title">{{ $network['settings']['empty_state_title'] }}</div>
        <p data-network-empty-description>{{ $network['settings']['empty_state_description'] }}</p>
        @if($network['empty_action'] && $network['empty_action']['kind'] === 'link')<a href="{{ $network['empty_action']['href'] }}" target="{{ $network['empty_action']['target'] }}" rel="{{ $network['empty_action']['rel'] }}">پیگیری</a>@endif
    </div>

    <dialog id="{{ $id }}-type-dialog" class="bn-filter-dialog" data-type-dialog aria-labelledby="{{ $id }}-type-title">
        <form method="dialog" class="bn-filter-dialog__surface">
            <header><div id="{{ $id }}-type-title" class="business-network-filter-dialog__title">فیلتر نوع مرکز</div><button type="submit" class="bn-dialog-close" value="cancel" aria-label="بستن پنجره">×</button></header>
            <div class="bn-type-options">
                @foreach($network['type_labels'] as $type => $label)
                    <label class="business-network-filter-option"><input type="checkbox" value="{{ $type }}" data-type-option><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <footer><button type="button" class="bn-filter-clear" data-type-filter-clear>حذف فیلتر</button><button type="button" class="bn-filter-apply" data-type-filter-apply>اعمال فیلتر</button></footer>
        </form>
    </dialog>
</section>

<script>
(() => {
    const root = document.getElementById(@json($id));
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    const provinceControls = [...root.querySelectorAll('[data-province-code]')];
    const provinceNames = new Map([...root.querySelectorAll('.bn-provinces [data-province-code]')].map(button => [button.dataset.provinceCode, button.querySelector('span').textContent.trim()]));
    const cards = [...root.querySelectorAll('.bn-card')];
    const search = root.querySelector('[data-network-search]');
    const summary = root.querySelector('[data-network-summary]');
    const empty = root.querySelector('[data-network-empty]');
    const emptyDescription = root.querySelector('[data-network-empty-description]');
    const dialog = root.querySelector('[data-type-dialog]');
    const typeOptions = [...root.querySelectorAll('[data-type-option]')];
    const badge = root.querySelector('[data-type-filter-badge]');
    const selectedProvinces = new Set(root.dataset.initialProvince ? [root.dataset.initialProvince] : []);
    const selectedTypes = new Set();

    const render = () => {
        provinceControls.forEach(control => {
            const active = selectedProvinces.has(control.dataset.provinceCode);
            control.classList.toggle('is-active', active);
            control.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const query = (search?.value || '').trim().toLocaleLowerCase('fa');
        let count = 0;
        cards.forEach(card => {
            const visible = selectedProvinces.has(card.dataset.province)
                && (selectedTypes.size === 0 || selectedTypes.has(card.dataset.type))
                && (!query || card.dataset.searchText.toLocaleLowerCase('fa').includes(query));
            card.hidden = !visible;
            if (visible) count++;
        });
        const provinceCount = selectedProvinces.size;
        summary.textContent = provinceCount === 1
            ? `${count.toLocaleString('fa-IR')} مورد در ${provinceNames.get([...selectedProvinces][0]) || 'استان منتخب'}`
            : `${count.toLocaleString('fa-IR')} مورد در ${provinceCount.toLocaleString('fa-IR')} استان منتخب`;
        empty.hidden = count > 0;
        emptyDescription.textContent = provinceCount === 0
            ? 'برای مشاهده مراکز، حداقل یک استان را انتخاب کنید.'
            : 'در حال حاضر مورد فعالی با فیلترهای انتخاب‌شده ثبت نشده است.';
        badge.hidden = selectedTypes.size === 0;
        badge.textContent = selectedTypes.size.toLocaleString('fa-IR');
    };

    const toggleProvince = code => {
        selectedProvinces.has(code) ? selectedProvinces.delete(code) : selectedProvinces.add(code);
        render();
    };
    provinceControls.forEach(control => {
        const toggle = () => toggleProvince(control.dataset.provinceCode);
        control.addEventListener('click', toggle);
        if (control.namespaceURI === 'http://www.w3.org/2000/svg') control.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle(); }
        });
    });
    root.querySelector('[data-clear-provinces]')?.addEventListener('click', () => { selectedProvinces.clear(); render(); });
    search?.addEventListener('input', render);

    root.querySelector('[data-type-filter-open]')?.addEventListener('click', () => {
        typeOptions.forEach(option => { option.checked = selectedTypes.has(option.value); });
        dialog.showModal();
    });
    root.querySelector('[data-type-filter-apply]')?.addEventListener('click', () => {
        selectedTypes.clear();
        typeOptions.filter(option => option.checked).forEach(option => selectedTypes.add(option.value));
        dialog.close(); render();
    });
    root.querySelector('[data-type-filter-clear]')?.addEventListener('click', () => {
        selectedTypes.clear(); typeOptions.forEach(option => { option.checked = false; }); dialog.close(); render();
    });
    dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    render();
})();
</script>

<style>
.business-network{max-width:1200px;margin:auto;padding:2rem 1rem;font-size:var(--theme-base-font-size,16px);line-height:1.75}.business-network button{font-size:inherit}.bn-description{margin-block:.5rem 1.25rem}.bn-search input{width:100%;padding:.8rem 1rem;border:1px solid #cbd5e1;border-radius:.75rem}.bn-explorer{direction:ltr;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem;margin-block:1.5rem}.bn-map-panel,.bn-province-panel{direction:rtl;min-width:0;padding:1rem;border:1px solid #e2e8f0;border-radius:1rem;background:#fff}.business-network-panel__title,.business-network-provinces__title{margin:0 0 .75rem;color:var(--theme-heading,#111827);font-size:1em;font-weight:800;line-height:1.6}.bn-map-shell{min-width:0;overflow:hidden}.bn-map-shell svg{display:block;width:100%;height:auto;max-height:440px}.bn-map-shell .iran-map__province{fill:#e2e8f0;stroke:#fff;stroke-width:2;cursor:pointer;transition:fill .15s ease}.bn-map-shell .iran-map__province:hover,.bn-map-shell .iran-map__province.is-active{fill:var(--theme-primary,#2563eb)}.bn-map-shell .iran-map__province:focus{outline:none;stroke:var(--theme-secondary,#111827);stroke-width:5}.bn-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:.75rem}.bn-clear-provinces{border:0;background:transparent;color:var(--theme-link,#2563eb);cursor:pointer}.bn-provinces{height:410px;overflow-y:auto;overscroll-behavior:contain;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-content:start;gap:.55rem;padding:.15rem .1rem .15rem .35rem}.bn-provinces button{display:flex;align-items:center;justify-content:space-between;gap:.35rem;min-width:0;min-height:44px;padding:.55rem .65rem;border:1px solid #e2e8f0;border-radius:999px;background:#f8fafc;color:inherit;cursor:pointer;line-height:1.5}.bn-provinces button span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.bn-provinces button b{font-size:.86em}.bn-provinces button:hover{border-color:var(--theme-primary,#2563eb);background:#eff6ff}.bn-provinces button.is-active{border-color:var(--theme-primary,#2563eb);background:var(--theme-primary,#2563eb);color:#fff}.bn-provinces button:focus-visible,.bn-type-filter-trigger:focus-visible,.bn-filter-dialog button:focus-visible,.business-network-filter-option:has(input:focus-visible){outline:3px solid color-mix(in srgb,var(--theme-primary,#2563eb) 35%,transparent);outline-offset:2px}.bn-summary-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-block:1.25rem}.bn-summary{margin:0;font-weight:700}.bn-type-filter-trigger{display:inline-flex;align-items:center;gap:.45rem;min-height:44px;padding:.6rem .9rem;border:1px solid #cbd5e1;border-radius:.7rem;background:#fff;cursor:pointer}.bn-filter-badge{display:grid;place-items:center;min-width:1.4rem;height:1.4rem;padding-inline:.25rem;border-radius:999px;background:var(--theme-primary,#2563eb);color:#fff;font-size:.75em}.bn-cards{display:grid;grid-template-columns:repeat(var(--bn-columns),minmax(0,1fr));gap:1rem;max-height:680px;min-width:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;padding-inline-end:.4rem;scrollbar-width:thin;scrollbar-color:#94a3b8 transparent}.bn-cards::-webkit-scrollbar{width:8px}.bn-cards::-webkit-scrollbar-track{background:transparent}.bn-cards::-webkit-scrollbar-thumb{background:#94a3b8;border:2px solid transparent;border-radius:999px;background-clip:padding-box}.bn-cards::-webkit-scrollbar-thumb:hover{background:#64748b;background-clip:padding-box}.bn-card{min-width:0;padding:1rem;border:1px solid #e2e8f0;border-radius:.75rem;overflow-wrap:anywhere}.business-network-card__title{color:var(--theme-heading,#111827);font-size:1.08em;font-weight:800;line-height:1.55;margin-block-end:.45rem}.bn-card a,.bn-card address{display:block}.bn-empty{text-align:center;padding:2.5rem 1rem;border:1px dashed #cbd5e1;border-radius:1rem}.business-network-empty__title{color:var(--theme-heading,#111827);font-size:1.08em;font-weight:800;margin-block-end:.35rem}.bn-filter-dialog{width:min(92vw,520px);padding:0;border:0;border-radius:1rem;color:inherit}.bn-filter-dialog::backdrop{background:rgb(15 23 42/.58);backdrop-filter:blur(2px)}.bn-filter-dialog__surface{padding:1.25rem}.bn-filter-dialog header,.bn-filter-dialog footer{display:flex;align-items:center;justify-content:space-between;gap:.75rem}.business-network-filter-dialog__title{margin:0;color:var(--theme-heading,#111827);font-size:1em;font-weight:800;line-height:1.6}.bn-dialog-close{width:44px;height:44px;border:0;border-radius:999px;background:#f1f5f9;font-size:1.5em;cursor:pointer}.bn-type-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin-block:1.25rem}.business-network-filter-option{direction:rtl;display:flex;align-items:center;justify-content:flex-start;gap:.55rem;min-width:0;min-height:48px;padding:.65rem;border:1px solid #e2e8f0;border-radius:.75rem;text-align:right;cursor:pointer}.business-network-filter-option input{flex:0 0 auto;width:1rem;height:1rem;margin:0;accent-color:var(--theme-primary,#2563eb)}.business-network-filter-option span{min-width:0}.business-network-filter-option:has(input:checked){border-color:var(--theme-primary,#2563eb);background:#eff6ff}.bn-filter-dialog footer{justify-content:flex-end}.bn-filter-clear,.bn-filter-apply{min-height:44px;padding:.65rem 1rem;border-radius:.7rem;cursor:pointer}.bn-filter-clear{border:1px solid #cbd5e1;background:#fff}.bn-filter-apply{border:1px solid var(--theme-primary,#2563eb);background:var(--theme-primary,#2563eb);color:#fff}
@media(max-width:1024px){.bn-cards{max-height:600px}}
@media(max-width:800px){.bn-explorer{grid-template-columns:1fr}.bn-map-panel{order:1}.bn-province-panel{order:2}.bn-map-shell svg{max-height:none}.bn-provinces{height:260px;grid-template-columns:repeat(2,minmax(0,1fr))}.bn-summary-row{align-items:flex-start}.bn-cards{grid-template-columns:1fr;max-height:520px}.bn-filter-dialog{width:calc(100vw - 1rem);max-width:none;margin:auto .5rem .5rem}.bn-type-options{grid-template-columns:1fr}}
@media(max-width:560px){.business-network{font-size:var(--theme-base-font-size-mobile,15px)}}
</style>
@endif
