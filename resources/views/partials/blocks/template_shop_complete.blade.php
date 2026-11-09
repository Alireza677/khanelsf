@php
    $items = $context['items'] ?? collect();
    $categories = $context['categories'] ?? collect();
    $filters = $context['filters'] ?? [];
    $favoriteProductIds = $context['favoriteProductIds'] ?? [];
    $activeCategory = $context['activeCategory'] ?? null;
    $heading = $data['title'] ?? $context['heading'] ?? 'فروشگاه';
    $description = $data['description'] ?? $context['description'] ?? null;
    $backgroundImage = \App\Models\Media::reusableImage($data['background_media_id'] ?? null)?->getUrl()
        ?? ($data['background_image'] ?? null);
    $allCategoriesImage = \App\Models\Media::reusableImage($data['all_categories_media_id'] ?? null)?->getUrl()
        ?? ($data['all_categories_image'] ?? null);
    $overlayOpacity = (int) ($data['overlay_opacity'] ?? 20);
    $overlayOpacity = max(0, min(90, $overlayOpacity));
    $overlayColor = "color-mix(in srgb, var(--theme-secondary, #111827) {$overlayOpacity}%, transparent)";
    $style = filled($backgroundImage)
        ? "background-image: linear-gradient({$overlayColor}, {$overlayColor}), url('".e($backgroundImage)."');"
        : null;
    $filterUi = $context['filterUi'] ?? [];
    $filters = array_replace([
        'q' => '', 'category' => $activeCategory?->slug ?? '', 'brands' => [], 'tags' => [], 'attributes' => [],
        'min_price' => null, 'max_price' => null, 'stock' => '', 'featured' => false, 'favorites' => false,
        'on_sale' => false, 'include_children' => true, 'sort' => 'default',
    ], $filters);
    $query = $filters;
    $hasActiveProductFilters = ($filterUi['isFiltered'] ?? false) || $filters['category'] !== '';
@endphp

@if (($context['type'] ?? null) === 'products')
    <section class="content-block shop-template" dir="rtl" data-shop-filter-drawer>
        <header class="shop-template__hero" @if ($style) style="{!! $style !!}" @endif>
            <div class="shop-template__hero-inner">
                <div class="shop-template__copy">
                    @if (! empty($data['eyebrow']))
                        <p class="shop-template__eyebrow">{{ $data['eyebrow'] }}</p>
                    @endif

                    @include('partials.blocks._heading', ['title' => $heading, 'tag' => $data['heading_tag'] ?? 'h1'])

                    @if ($description)
                        <p>{{ $description }}</p>
                    @endif
                </div>

                <form class="shop-template__search" action="{{ $filterUi['action'] ?? route('shop.index') }}" method="get">
                    @foreach ($filterUi['searchHidden'] ?? [] as $input)
                        <input type="hidden" name="{{ $input['name'] }}" value="{{ $input['value'] }}">
                    @endforeach
                    <label class="sr-only" for="shop-template-q">جستجوی محصولات</label>
                    <span class="shop-template__search-field">
                        <i class="shop-template__search-icon icon-search-normal" aria-hidden="true"></i>
                        <input
                            id="shop-template-q"
                            name="q"
                            type="search"
                            maxlength="200"
                            value="{{ $query['q'] }}"
                            placeholder="{{ $data['search_placeholder'] ?? 'جستجو در بین هزاران محصول...' }}"
                        >
                    </span>

                    <label class="sr-only" for="shop-template-category">دسته‌بندی محصول</label>
                    <span class="shop-template__select-field">
                        <select id="shop-template-category" name="category">
                            <option value="">{{ $data['category_label'] ?? 'انتخاب دسته‌بندی' }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->slug }}" @selected($query['category'] === $category->slug)>
                                    {{ $filterUi['categoryLabels'][$category->id] ?? $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <i class="shop-template__select-icon icon-arrow-down-1" aria-hidden="true"></i>
                    </span>

                    <button type="submit" aria-label="جستجوی محصولات">
                        <span>جستجو</span>
                        <i class="icon-search-normal" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </header>

        @if ($categories->isNotEmpty())
            <section class="shop-template__categories" aria-labelledby="shop-template-categories-title" data-shop-category-slider>
                <div class="shop-template__section-heading shop-template__section-heading--center">
                    @php
                        $requestedCategoryHeadingTag = $data['category_heading_tag'] ?? 'h2';
                        $categoryHeadingTag = \App\CMS\Blocks\Support\HeadingLevel::normalize($requestedCategoryHeadingTag);
                    @endphp
                    <{{ $categoryHeadingTag }} id="shop-template-categories-title" class="block-title">{{ $data['category_section_title'] ?? 'خرید بر اساس دسته‌بندی' }}</{{ $categoryHeadingTag }}>
                    <span class="shop-template__heading-rule" aria-hidden="true"></span>
                    <div class="shop-template__category-controls">
                        <button class="shop-template__category-control shop-template__category-control--prev" type="button" aria-label="دسته‌بندی‌های قبلی" data-shop-category-prev>
                            <i class="icon-arrow-left-2" aria-hidden="true"></i>
                        </button>

                        <button class="shop-template__category-control shop-template__category-control--next" type="button" aria-label="دسته‌بندی‌های بعدی" data-shop-category-next>
                            <i class="icon-arrow-right-3" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="shop-template__category-slider">
                    <div class="shop-template__category-viewport" data-shop-category-viewport>
                        <div class="shop-template__category-row" data-shop-category-track>
                            <a @class(['shop-template__category', 'shop-template__category--all', 'is-active' => ! $activeCategory]) href="{{ $filterUi['allCategoriesUrl'] ?? route('shop.index') }}" @if ($hasActiveProductFilters) rel="nofollow" @endif>
                                <span class="shop-template__category-media">
                                    @if (filled($allCategoriesImage))
                                        <img src="{{ $allCategoriesImage }}" alt="همه محصولات">
                                    @else
                                        <span class="shop-template__category-grid-icon" aria-hidden="true">
                                            <span></span><span></span><span></span><span></span>
                                        </span>
                                    @endif
                                </span>
                                <span>همه محصولات</span>
                            </a>

                            @foreach ($categories as $category)
                                <a @class(['shop-template__category', 'is-active' => $activeCategory?->is($category)]) href="{{ $filterUi['categoryUrls'][$category->id] ?? route('shop.category', $category->slug) }}" @if ($hasActiveProductFilters) rel="nofollow" @endif>
                                    <span class="shop-template__category-media">
                                        @if (! empty($category->seo_image))
                                            <img src="{{ $category->seo_image }}" alt="{{ $category->name }}">
                                        @else
                                            <span>{{ mb_substr($category->name, 0, 1) }}</span>
                                        @endif
                                    </span>
                                    <span>{{ $category->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                </div>
            </section>
        @endif

        <section class="shop-template__catalog" aria-labelledby="shop-template-products-title">
            <button
                class="shop-template__filter-backdrop"
                type="button"
                aria-label="بستن فیلتر محصولات"
                tabindex="-1"
                data-shop-filter-close
            ></button>

            <aside
                id="shop-product-filters"
                class="shop-template__filters"
                aria-labelledby="shop-product-filters-title"
                role="complementary"
                tabindex="-1"
                data-shop-filter-panel
            >
                <header class="shop-template__filter-header">
                    <h2 id="shop-product-filters-title">فیلتر محصولات</h2>
                    <button class="shop-template__filter-close" type="button" aria-label="بستن فیلتر محصولات" data-shop-filter-close>
                        <span aria-hidden="true">×</span>
                    </button>
                </header>

                @include('shop.partials.filter-form')
            </aside>

            <div class="shop-template__products">
                <h2 id="shop-template-products-title" class="sr-only">{{ filled($data['products_title'] ?? null) ? $data['products_title'] : 'محصولات فروشگاه' }}</h2>
                <div class="shop-template__toolbar">
                    <div class="shop-template__toolbar-actions">
                        <button
                            @class(['shop-template__filter-toggle', 'has-active-filters' => $hasActiveProductFilters])
                            type="button"
                            aria-expanded="false"
                            aria-controls="shop-product-filters"
                            aria-haspopup="dialog"
                            aria-label="{{ $hasActiveProductFilters ? 'فیلتر محصولات، فیلتر فعال است' : 'فیلتر محصولات' }}"
                            data-shop-filter-toggle
                        >
                            <i class="icon-filter" aria-hidden="true"></i>
                            <span>فیلتر محصولات</span>
                        </button>

                        @include('shop.partials.filter-sort')

                        <a
                            @class(['shop-template__favorites-filter', 'is-active' => $query['favorites']])
                            href="{{ $filterUi['favoriteUrl'] ?? route('shop.index', ['favorites' => 1]) }}" rel="nofollow"
                            aria-pressed="{{ $query['favorites'] ? 'true' : 'false' }}"
                            aria-label="{{ $query['favorites'] ? 'نمایش همه محصولات' : 'مشاهده علاقه‌مندی‌ها' }}"
                        >
                            <i class="icon-heart" aria-hidden="true"></i>
                            <span>{{ $query['favorites'] ? 'نمایش همه محصولات' : 'مشاهده علاقه‌مندی‌ها' }}</span>
                        </a>
                    </div>

                </div>

                @include('shop.partials.filter-summary')

                <div class="shop-template__product-scroll">
                    <div class="shop-template__product-grid">
                        @forelse ($items as $product)
                            @php($isFavorite = in_array($product->getKey(), $favoriteProductIds, true))
                            <article @class(['shop-template__product-card', 'is-favorite' => $isFavorite])>
                                <form class="shop-template__favorite-form" action="{{ route('shop.favorites.toggle', $product) }}" method="post">
                                    @csrf
                                    <button
                                        class="shop-template__heart"
                                        type="submit"
                                        aria-label="{{ $isFavorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها' }}"
                                        aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
                                        title="{{ $isFavorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها' }}"
                                    >
                                        <i class="icon-heart" aria-hidden="true"></i>
                                    </button>
                                </form>

                                <a class="shop-template__product-image" href="{{ route('shop.show', $product->slug) }}" aria-label="{{ $product->title }}">
                                    @if ($product->is_featured)
                                        <span class="shop-template__badge">ویژه</span>
                                    @endif

                                    @if ($product->featuredImageUrl('thumb'))
                                        <img src="{{ $product->featuredImageUrl('thumb') }}" alt="{{ $product->title }}">
                                    @else
                                        <span class="shop-template__image-placeholder">{{ $product->title }}</span>
                                    @endif
                                </a>

                                <div class="shop-template__product-body">
                                    <h3><a href="{{ route('shop.show', $product->slug) }}">{{ $product->title }}</a></h3>

                                    <p class="shop-template__price">
                                        @include('shop.partials.price', ['product' => $product])
                                    </p>

                                    <p @class(['shop-template__stock', 'is-out' => ! ($product->has_stock && $product->stock_status === 'in_stock')])>
                                        <span></span>
                                        {{ $product->has_stock && $product->stock_status === 'in_stock' ? 'موجود' : 'ناموجود' }}
                                    </p>
                                </div>
                            </article>
                        @empty
                            <p class="blog-index__empty">{{ $data['empty_message'] ?? $context['emptyMessage'] ?? 'محصولی با فیلترهای انتخاب‌شده پیدا نشد.' }}</p>
                        @endforelse
                    </div>
                </div>

                @if (is_object($items) && method_exists($items, 'links'))
                    <div class="blog-index__pagination">
                        {{ $items->links() }}
                    </div>
                @endif
            </div>
        </section>
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">قالب کامل فروشگاه فقط برای آرشیو محصولات فروشگاه کار می‌کند.</p>
@endif
