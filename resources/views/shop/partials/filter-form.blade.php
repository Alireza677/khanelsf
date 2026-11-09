<form action="{{ $filterUi['action'] ?? route('shop.index') }}" method="get">
    @foreach ($filterUi['filterHidden'] ?? [] as $input)
        <input type="hidden" name="{{ $input['name'] }}" value="{{ $input['value'] }}">
    @endforeach
    <label class="sr-only" for="shop-sidebar-q">جستجوی محصول</label>
    <span class="shop-template__sidebar-search">
        <input id="shop-sidebar-q" name="q" type="search" maxlength="200" value="{{ $filters['q'] }}" placeholder="جستجوی محصول...">
        <i class="icon-search-normal" aria-hidden="true"></i>
    </span>
    <details class="shop-template__filter-group" open>
        <summary>دسته‌بندی</summary>
        <div class="shop-template__filter-options">
            <label><input name="category" type="radio" value="" @checked($filters['category'] === '')> همه دسته‌بندی‌ها</label>
            @foreach ($categories as $category)
                <label><input name="category" type="radio" value="{{ $category->slug }}" @checked($filters['category'] === $category->slug)> {{ $filterUi['categoryLabels'][$category->id] ?? $category->name }}</label>
            @endforeach
        </div>
        @if ($filters['category'] !== '')
            <input type="hidden" name="include_children" value="0">
            <label><input name="include_children" type="checkbox" value="1" @checked($filters['include_children'])> نمایش محصولات زیرشاخه‌ها</label>
        @endif
    </details>
    <details class="shop-template__filter-group" @if ($filters['min_price'] !== null || $filters['max_price'] !== null) open @endif>
        <summary>محدوده قیمت (تومان)</summary>
        <div class="shop-template__price-fields">
            <label>از <input name="min_price" type="number" min="0" step="0.01" value="{{ $filters['min_price'] }}" placeholder="حداقل قیمت"></label>
            <label>تا <input name="max_price" type="number" min="0" step="0.01" value="{{ $filters['max_price'] }}" placeholder="حداکثر قیمت"></label>
        </div>
    </details>
    @foreach (['brands' => 'برند', 'tags' => 'برچسب'] as $key => $label)
        @if (($filterUi['facets'][$key] ?? collect())->isNotEmpty())
            <details class="shop-template__filter-group" @if ($filters[$key]) open @endif>
                <summary>{{ $label }}</summary>
                <div class="shop-template__filter-options">
                    @foreach ($filterUi['facets'][$key] as $option)
                        <label><input name="{{ $key }}[]" type="checkbox" value="{{ $option->slug }}" @checked(in_array($option->slug, $filters[$key], true))> {{ $option->name }}</label>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
    @foreach ($filterUi['facets']['attributes'] ?? [] as $attribute)
        <details class="shop-template__filter-group" @if (! empty($filters['attributes'][$attribute->slug])) open @endif>
            <summary>{{ $attribute->name }}{{ $attribute->unit ? ' ('.$attribute->unit.')' : '' }}</summary>
            <div class="shop-template__filter-options">
                @foreach ($attribute->values as $value)
                    <label><input name="attributes[{{ $attribute->slug }}][]" type="checkbox" value="{{ $value->slug }}" @checked(in_array($value->slug, $filters['attributes'][$attribute->slug] ?? [], true))> {{ $value->name }}</label>
                @endforeach
            </div>
        </details>
    @endforeach
    <details class="shop-template__filter-group" @if ($filters['stock']) open @endif>
        <summary>وضعیت موجودی</summary>
        <div class="shop-template__filter-options">
            <label><input name="stock" type="radio" value="" @checked($filters['stock'] === '')> همه محصولات</label>
            <label><input name="stock" type="radio" value="in_stock" @checked($filters['stock'] === 'in_stock')> موجود</label>
            <label><input name="stock" type="radio" value="out_of_stock" @checked($filters['stock'] === 'out_of_stock')> ناموجود</label>
        </div>
    </details>
    <details class="shop-template__filter-group" @if ($filters['featured'] || $filters['on_sale']) open @endif>
        <summary>ویژه و تخفیف‌دار</summary>
        <div class="shop-template__filter-options">
            <label><input name="featured" type="checkbox" value="1" @checked($filters['featured'])> فقط محصولات ویژه</label>
            <label><input name="on_sale" type="checkbox" value="1" @checked($filters['on_sale'])> فقط محصولات تخفیف‌دار</label>
        </div>
    </details>
    <div class="shop-template__filter-actions">
        <button class="button" type="submit">اعمال فیلترها</button>
        <a class="button button-secondary" rel="nofollow" href="{{ $filterUi['clearUrl'] ?? route('shop.index') }}">حذف فیلترها</a>
    </div>
</form>
