@php
    use Filament\Forms\Components\Actions\Action;

    $containers = $getChildComponentContainers();
    $cloneAction = $getAction($getCloneActionName());
    $deleteAction = $getAction($getDeleteActionName());
    $reorderAction = $getAction($getReorderActionName());
    $isCloneable = $isCloneable();
    $isDeletable = $isDeletable();
    $isReorderableWithDragAndDrop = $isReorderableWithDragAndDrop();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="form-builder-editor-{{ $statePath }}"
        x-data="{
            activeItem: null,
            activeTab: 'add',
            search: '',
            choicesOpen: false,
            choicesItem: null,
            choicesDrawerStyle: '',
            selectField(key) {
                if (this.choicesOpen && this.choicesItem !== key) this.closeChoices()
                this.activeItem = key
                this.activeTab = 'settings'
            },
            clearSelection() {
                this.closeChoices()
                this.activeItem = null
                this.activeTab = 'add'
            },
            openChoices(key) {
                this.choicesItem = key
                this.choicesOpen = true
                this.$nextTick(() => this.positionChoicesDrawer())
            },
            closeChoices() {
                this.choicesOpen = false
                this.choicesItem = null
            },
            positionChoicesDrawer() {
                if (window.innerWidth < 1024) {
                    this.choicesDrawerStyle = 'inset: 1rem; width: auto; max-height: calc(100vh - 2rem);'
                    return
                }

                const inspector = this.$refs.inspector.getBoundingClientRect()
                const left = inspector.right + 16
                const width = Math.min(480, window.innerWidth - left - 16)
                this.choicesDrawerStyle = `top: ${inspector.top}px; left: ${left}px; width: ${Math.max(320, width)}px; max-height: ${window.innerHeight - inspector.top - 16}px;`
            },
            matches(label, type) {
                const query = this.search.trim().toLocaleLowerCase('fa')
                return query === '' || `${label} ${type}`.toLocaleLowerCase('fa').includes(query)
            },
        }"
        x-on:form-builder-field-added.window="selectField($event.detail.key)"
        x-on:resize.window="choicesOpen && positionChoicesDrawer()"
        {{ $attributes->merge($getExtraAttributes(), escape: false)->class(['form-builder-editor']) }}
    >
        <aside x-ref="inspector" class="form-builder-inspector" aria-label="ویرایشگر فیلد">
            <div class="form-builder-tabs" role="tablist" aria-label="ابزارهای فرم">
                <button
                    type="button"
                    role="tab"
                    x-on:click="activeTab = 'add'"
                    x-bind:aria-selected="activeTab === 'add'"
                    x-bind:class="{ 'is-active': activeTab === 'add' }"
                >
                    افزودن فیلد
                </button>
                <button
                    type="button"
                    role="tab"
                    x-on:click="activeTab = 'settings'"
                    x-bind:aria-selected="activeTab === 'settings'"
                    x-bind:class="{ 'is-active': activeTab === 'settings' }"
                >
                    تنظیمات فیلد
                </button>
            </div>

            <div class="form-builder-inspector__body">
                <div x-show="activeTab === 'add'" role="tabpanel">
                    <label class="form-builder-search">
                        <span class="sr-only">جستجوی فیلد</span>
                        <x-filament::icon icon="heroicon-m-magnifying-glass" />
                        <input type="search" x-model="search" placeholder="جستجوی فیلد…">
                    </label>

                    <div class="form-builder-palette">
                        @foreach ($fieldPalette as $categoryKey => $category)
                            <details open>
                                <summary>{{ $category['label'] }}</summary>
                                <div class="form-builder-palette__items">
                                    @foreach ($category['fields'] as $type => $definition)
                                        <button
                                            type="button"
                                            x-show="matches(@js($definition['label']), @js($type))"
                                            wire:click="mountFormComponentAction('{{ $statePath }}', 'add', { fieldType: '{{ $type }}' })"
                                            wire:loading.attr="disabled"
                                            wire:target="mountFormComponentAction"
                                        >
                                            <x-filament::icon :icon="$definition['icon']" />
                                            <span>{{ $definition['label'] }}</span>
                                            <x-filament::icon icon="heroicon-m-plus" />
                                        </button>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>

                <div x-show="activeTab === 'settings'" x-cloak role="tabpanel">
                    <div x-show="activeItem === null" class="form-builder-inspector__empty">
                        <x-filament::icon icon="heroicon-o-cursor-arrow-rays" />
                        <p>برای ویرایش، یک فیلد یا مرحله را از بوم انتخاب کنید.</p>
                    </div>

                    @foreach ($containers as $uuid => $item)
                        <div
                            x-show="activeItem === @js($uuid)"
                            x-cloak
                            class="form-builder-field-settings"
                            wire:key="{{ $this->getId() }}.{{ $item->getStatePath() }}.settings"
                        >
                            {{ $item }}
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>

        <section
            class="form-builder-canvas"
            aria-label="بوم فرم"
            x-on:click.self="clearSelection()"
        >
            <header class="form-builder-canvas__header" x-on:click.self="clearSelection()">
                <div>
                    <h3>ساختار فرم</h3>
                    <p>برای ویرایش تنظیمات، یک فیلد را انتخاب کنید.</p>
                </div>
                <span>{{ count($containers) }} فیلد</span>
            </header>

            @if (count($containers))
                <ul
                    class="form-builder-canvas__items"
                    wire:end.stop="mountFormComponentAction('{{ $statePath }}', 'reorder', { items: $event.target.sortable.toArray() })"
                    x-sortable
                    data-sortable-animation-duration="150"
                    x-on:click.self="clearSelection()"
                >
                    @foreach ($containers as $uuid => $item)
                        @php
                            $itemState = $item->getRawState();
                            $itemState = $itemState instanceof \Illuminate\Contracts\Support\Arrayable ? $itemState->toArray() : $itemState;
                            $itemState = is_array($itemState) ? $itemState : [];
                            $type = $itemState['type'] ?? 'text';
                            $label = filled($itemState['label'] ?? null) ? $itemState['label'] : ($fieldTypeLabels[$type] ?? 'فیلد بدون عنوان');
                            $isStructural = in_array($type, ['page', 'step'], true);
                            $span = $isStructural ? 12 : \App\Services\FormSchema::normalizeColumnSpan(data_get($itemState, 'layout.span'));
                            $widthLabel = [12 => '۱۰۰٪', 9 => '۷۵٪', 8 => '۶۶٪', 6 => '۵۰٪', 4 => '۳۳٪', 3 => '۲۵٪'][$span];
                            $optionLabels = collect($itemState['options'] ?? [])
                                ->map(fn ($option): ?string => is_string($option) ? $option : data_get($option, 'label'))
                                ->filter()
                                ->take(3)
                                ->values();
                            $itemCloneAction = $cloneAction(['item' => $uuid]);
                            $itemDeleteAction = $deleteAction(['item' => $uuid]);
                        @endphp

                        <li
                            wire:ignore.self
                            wire:key="{{ $this->getId() }}.{{ $item->getStatePath() }}.canvas"
                            x-sortable-item="{{ $uuid }}"
                            x-on:click="selectField(@js($uuid))"
                            x-bind:class="{ 'is-selected': activeItem === @js($uuid) }"
                            @class([
                                'form-builder-card',
                                'is-structural' => $isStructural,
                                "form-builder-card--span-{$span}",
                            ])
                        >
                            @if ($isStructural)
                                <span class="form-builder-card__handle" x-sortable-handle x-on:click.stop>
                                    <x-filament::icon icon="heroicon-m-bars-3" />
                                </span>
                                <div class="form-builder-step-divider">
                                    <span>{{ $fieldTypeLabels[$type] ?? 'مرحله' }}</span>
                                    <strong>{{ $label }}</strong>
                                </div>
                            @else
                                <div class="form-builder-card__topline">
                                    <span class="form-builder-card__handle" x-sortable-handle x-on:click.stop>
                                        <x-filament::icon icon="heroicon-m-bars-3" />
                                    </span>
                                    <div class="form-builder-card__title">
                                        <strong>{{ $label }}</strong>
                                        <span>{{ $fieldTypeLabels[$type] ?? $type }}</span>
                                    </div>
                                    <div class="form-builder-card__meta">
                                        @if (filter_var($itemState['required'] ?? false, FILTER_VALIDATE_BOOLEAN))
                                            <span class="is-required">الزامی</span>
                                        @endif
                                        <span>{{ $widthLabel }}</span>
                                    </div>
                                </div>

                                <div class="form-builder-card__preview" aria-hidden="true">
                                    @if ($type === 'textarea')
                                        <span class="is-textarea"></span>
                                    @elseif ($type === 'select')
                                        <span>{{ $optionLabels->first() ?? 'انتخاب کنید' }}</span>
                                        <x-filament::icon icon="heroicon-m-chevron-down" />
                                    @elseif (in_array($type, ['image_choice', 'radio_card'], true))
                                        @forelse ($optionLabels as $optionLabel)
                                            <span class="is-choice">{{ $optionLabel }}</span>
                                        @empty
                                            <span class="is-choice">بدون گزینه</span>
                                        @endforelse
                                    @else
                                        <span>{{ $itemState['placeholder'] ?? '' }}</span>
                                    @endif
                                </div>
                            @endif

                            <div class="form-builder-card__actions">
                                @if ($isCloneable && $itemCloneAction->isVisible())
                                    <span x-on:click.stop>{{ $itemCloneAction }}</span>
                                @endif
                                @if ($isDeletable && $itemDeleteAction->isVisible())
                                    <span
                                        x-on:click.stop="if (activeItem === @js($uuid)) clearSelection()"
                                    >{{ $itemDeleteAction }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <button type="button" class="form-builder-canvas__empty" x-on:click="activeTab = 'add'">
                    <x-filament::icon icon="heroicon-o-plus-circle" />
                    <strong>اولین فیلد را اضافه کنید</strong>
                    <span>از پنل افزودن فیلد، نوع موردنظر را انتخاب کنید.</span>
                </button>
            @endif
        </section>
    </div>
</x-dynamic-component>
