<form class="shop-template__sort" action="{{ $filterUi['action'] ?? route('shop.index') }}" method="get">
    @foreach ($filterUi['sortHidden'] ?? [] as $input)
        <input type="hidden" name="{{ $input['name'] }}" value="{{ $input['value'] }}">
    @endforeach
    <i class="shop-template__sort-icon icon-sort" aria-hidden="true"></i>
    <label class="shop-template__sort-label" for="shop-sort">مرتب‌سازی:</label>
    <select id="shop-sort" name="sort" aria-label="مرتب‌سازی محصولات" data-shop-sort>
        @foreach ($filterUi['sortOptions'] ?? \App\Shop\ShopFilters::SORTS as $value => $label)
            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <i class="shop-template__sort-chevron icon-arrow-down-1" aria-hidden="true"></i>
    <button type="submit" data-shop-sort-submit>اعمال</button>
</form>
