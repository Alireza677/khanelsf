@php
    use App\Filament\Forms\Components\BlockInspectorTabs;

    $containers = $getChildComponentContainers();
    $blockPickerBlocks = $getBlockPickerBlocks();
    $blockPickerColumns = $getBlockPickerColumns();
    $blockPickerWidth = $getBlockPickerWidth();
    $builderState = $getState() ?? [];

    $addAction = $getAction($getAddActionName());
    $cloneAction = $getAction($getCloneActionName());
    $deleteAction = $getAction($getDeleteActionName());
    $reorderAction = $getAction($getReorderActionName());

    $isAddable = $isAddable();
    $isCloneable = $isCloneable();
    $isDeletable = $isDeletable();
    $isReorderableWithDragAndDrop = $isReorderableWithDragAndDrop();
    $statePath = $getStatePath();
    $initialItem = array_key_first($containers);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="block-builder-editor-{{ $statePath }}"
        x-data="{
            activeItem: @js($initialItem),
            activeInspectorTab: 'content',
            selectBlock(key) {
                this.activeItem = key
                this.activeInspectorTab = 'content'
            },
            clearSelection() {
                this.activeItem = null
                this.activeInspectorTab = 'content'
            },
        }"
        {{ $attributes->merge($getExtraAttributes(), escape: false)->class(['block-builder-editor']) }}
    >
        <aside class="block-builder-inspector" aria-label="Selected Block Inspector">
            @if (count($containers))
                @foreach ($containers as $uuid => $item)
                    @php
                        $itemState = $item->getRawState();
                        $itemState = $itemState instanceof \Illuminate\Contracts\Support\Arrayable ? $itemState->toArray() : $itemState;
                        $itemState = is_array($itemState) ? $itemState : [];
                        $block = $item->getParentComponent();
                        $type = data_get($builderState, "{$uuid}.type", $block->getName());
                        $label = $block->getLabel($itemState, $uuid);
                        $groups = BlockInspectorTabs::components($item);
                    @endphp

                    <section
                        x-show="activeItem === @js($uuid)"
                        x-cloak
                        class="block-builder-inspector__selection"
                        wire:key="{{ $this->getId() }}.{{ $item->getStatePath() }}.inspector"
                    >
                        <header class="block-builder-inspector__header">
                            <span>Selected Block</span>
                            <strong>{{ $label }}</strong>
                            <small>{{ $type }}</small>
                        </header>

                        <nav class="block-builder-inspector__tabs" role="tablist" aria-label="بخش‌های تنظیمات بلوک">
                            @foreach ([
                                BlockInspectorTabs::CONTENT => 'محتوا',
                                BlockInspectorTabs::DESIGN => 'طراحی',
                                BlockInspectorTabs::ADVANCED => 'تنظیمات پیشرفته',
                            ] as $tab => $tabLabel)
                                <button
                                    type="button"
                                    role="tab"
                                    x-on:click="activeInspectorTab = @js($tab)"
                                    x-bind:aria-selected="activeInspectorTab === @js($tab)"
                                    x-bind:class="{ 'is-active': activeInspectorTab === @js($tab) }"
                                >
                                    {{ $tabLabel }}
                                </button>
                            @endforeach
                        </nav>

                        <div class="block-builder-inspector__body">
                            @foreach ($groups as $tab => $components)
                                <div
                                    x-show="activeInspectorTab === @js($tab)"
                                    x-cloak
                                    class="block-builder-inspector__group"
                                    data-inspector-tab="{{ $tab }}"
                                >
                                    @forelse ($components as $formComponent)
                                        @php
                                            $isHidden = $formComponent->isHidden();
                                        @endphp
                                        <div
                                            @if ($formComponent instanceof \Filament\Forms\Components\Field)
                                                wire:key="{{ $this->getId() }}.{{ $formComponent->getStatePath() }}.{{ $formComponent::class }}"
                                            @endif
                                            @class(['hidden' => $isHidden])
                                        >
                                            @if (! $isHidden)
                                                {{ $formComponent }}
                                            @endif
                                        </div>
                                    @empty
                                        <p class="block-builder-inspector__empty-tab">
                                            تنظیماتی در این بخش وجود ندارد.
                                        </p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endif

            <div x-show="activeItem === null" x-cloak class="block-builder-inspector__empty">
                <x-filament::icon icon="heroicon-o-cursor-arrow-rays" />
                <strong>Selected Block</strong>
                <p>برای نمایش تنظیمات، یک بلوک را از Canvas انتخاب کنید.</p>
            </div>
        </aside>

        <section class="block-builder-canvas" aria-label="Block Canvas" x-on:click.self="clearSelection()">
            <header class="block-builder-canvas__header" x-on:click.self="clearSelection()">
                <div>
                    <h3>Block Canvas</h3>
                    <p>ساختار و ترتیب بلوک‌ها</p>
                </div>
                <span>{{ count($containers) }} بلوک</span>
            </header>

            @if (count($containers))
                <ul
                    class="block-builder-canvas__items"
                    wire:end.stop="mountFormComponentAction('{{ $statePath }}', 'reorder', { items: $event.target.sortable.toArray() })"
                    x-sortable
                    data-sortable-animation-duration="{{ $getReorderAnimationDuration() }}"
                    x-on:click.self="clearSelection()"
                >
                    @foreach ($containers as $uuid => $item)
                        @php
                            $itemState = $item->getRawState();
                            $itemState = $itemState instanceof \Illuminate\Contracts\Support\Arrayable ? $itemState->toArray() : $itemState;
                            $itemState = is_array($itemState) ? $itemState : [];
                            $block = $item->getParentComponent();
                            $type = data_get($builderState, "{$uuid}.type", $block->getName());
                            $label = $block->getLabel($itemState, $uuid);
                            $icon = $block->getIcon();
                            $itemCloneAction = $cloneAction(['item' => $uuid]);
                            $itemDeleteAction = $deleteAction(['item' => $uuid]);
                        @endphp

                        <li
                            wire:ignore.self
                            wire:key="{{ $this->getId() }}.{{ $item->getStatePath() }}.canvas"
                            x-sortable-item="{{ $uuid }}"
                            x-on:click="selectBlock(@js($uuid))"
                            x-bind:class="{ 'is-selected': activeItem === @js($uuid) }"
                            class="block-builder-card"
                        >
                            @if ($isReorderableWithDragAndDrop && $reorderAction->isVisible())
                                <span class="block-builder-card__handle" x-sortable-handle x-on:click.stop>
                                    {{ $reorderAction }}
                                </span>
                            @endif

                            <span class="block-builder-card__icon">
                                <x-filament::icon :icon="$icon ?: 'heroicon-o-cube'" />
                            </span>

                            <span class="block-builder-card__identity">
                                <strong>{{ $label }}</strong>
                                <small>{{ $type }}</small>
                            </span>

                            <span class="block-builder-card__actions">
                                @if ($isCloneable && $itemCloneAction->isVisible())
                                    <span x-on:click.stop>{{ $itemCloneAction }}</span>
                                @endif

                                @if ($isDeletable && $itemDeleteAction->isVisible())
                                    <span x-on:click.stop="if (activeItem === @js($uuid)) clearSelection()">
                                        {{ $itemDeleteAction }}
                                    </span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="block-builder-canvas__empty">
                    <x-filament::icon icon="heroicon-o-squares-plus" />
                    <strong>هنوز بلوکی اضافه نشده است.</strong>
                </div>
            @endif

            @if ($isAddable && $addAction->isVisible())
                <footer class="block-builder-canvas__footer">
                    <x-filament-forms::builder.block-picker
                        :action="$addAction"
                        :blocks="$blockPickerBlocks"
                        :columns="$blockPickerColumns"
                        :state-path="$statePath"
                        :width="$blockPickerWidth"
                    >
                        <x-slot name="trigger">
                            {{ $addAction }}
                        </x-slot>
                    </x-filament-forms::builder.block-picker>
                </footer>
            @endif
        </section>
    </div>
</x-dynamic-component>
