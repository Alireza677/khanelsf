@php
    $containers = $getChildComponentContainers();
    $addAction = $getAction($getAddActionName());
    $deleteAction = $getAction($getDeleteActionName());
    $reorderAction = $getAction($getReorderActionName());
    $isAddable = $isAddable();
    $isDeletable = $isDeletable();
    $isReorderableWithDragAndDrop = $isReorderableWithDragAndDrop();
    $statePath = $getStatePath();
    $fieldKey = str($statePath)->beforeLast('.options')->afterLast('.')->toString();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="form-builder-choices-control">
        <button
            type="button"
            class="form-builder-manage-choices"
            x-on:click="openChoices(@js($fieldKey))"
        >
            <span>
                <x-filament::icon icon="heroicon-m-list-bullet" />
                مدیریت گزینه‌ها
            </span>
            <span>{{ count($containers) }} گزینه</span>
        </button>

        @if (count($containers))
            <details class="form-builder-choice-metadata">
                <summary>تنظیمات تکمیلی موجود</summary>
                <p>تصویر و امتیازهای فعلی بدون تغییر در این بخش باقی مانده‌اند.</p>

                <div class="form-builder-choice-metadata__items">
                    @foreach ($containers as $uuid => $optionItem)
                        @php
                            $components = collect($optionItem->getComponents(withHidden: true))
                                ->filter(fn ($component): bool => method_exists($component, 'getName'))
                                ->keyBy(fn ($component): string => $component->getName());
                            $metadataComponents = $components
                                ->only(['image', 'scores'])
                                ->reject(fn ($component): bool => $component->isHidden());
                        @endphp

                        @if ($metadataComponents->isNotEmpty())
                            <div
                                class="form-builder-choice-metadata__item"
                                wire:key="{{ $this->getId() }}.{{ $optionItem->getStatePath() }}.metadata"
                            >
                                <strong>{{ data_get($optionItem->getRawState(), 'label', 'گزینه') }}</strong>
                                @foreach ($metadataComponents as $metadataComponent)
                                    {{ $metadataComponent }}
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif

        <template x-teleport="body">
            <div
                x-show="choicesOpen && choicesItem === @js($fieldKey)"
                x-cloak
                class="form-builder-choices-layer"
                x-on:keydown.escape.window="closeChoices()"
            >
                <button
                    type="button"
                    class="form-builder-choices-backdrop"
                    aria-label="بستن ویرایش انتخاب‌ها"
                    x-on:click="closeChoices()"
                    x-transition.opacity
                ></button>

                <section
                    class="form-builder-choices-drawer"
                    x-bind:style="choicesDrawerStyle"
                    role="dialog"
                    aria-modal="false"
                    aria-label="ویرایش انتخاب‌ها"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-x-4"
                    x-transition:enter-end="opacity-100 translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-x-0"
                    x-transition:leave-end="opacity-0 -translate-x-4"
                >
                    <header class="form-builder-choices-drawer__header">
                        <div>
                            <h3>ویرایش انتخاب‌ها</h3>
                            <p>عنوان گزینه‌ها را ویرایش یا ترتیب آن‌ها را تغییر دهید.</p>
                        </div>
                        <button type="button" x-on:click="closeChoices()" aria-label="بستن">
                            <x-filament::icon icon="heroicon-m-x-mark" />
                        </button>
                    </header>

                    <div class="form-builder-choices-drawer__body">
                        @if (count($containers))
                            <ul
                                class="form-builder-choice-list"
                                wire:end.stop="mountFormComponentAction('{{ $statePath }}', 'reorder', { items: $event.target.sortable.toArray() })"
                                x-sortable
                                data-sortable-animation-duration="150"
                            >
                                @foreach ($containers as $uuid => $optionItem)
                                    @php
                                        $components = collect($optionItem->getComponents(withHidden: true))
                                            ->filter(fn ($component): bool => method_exists($component, 'getName'))
                                            ->keyBy(fn ($component): string => $component->getName());
                                        $labelComponent = $components->get('label');
                                        $itemDeleteAction = $deleteAction(['item' => $uuid]);
                                    @endphp

                                    <li
                                        x-sortable-item="{{ $uuid }}"
                                        class="form-builder-choice-row"
                                        wire:key="{{ $this->getId() }}.{{ $optionItem->getStatePath() }}.choice"
                                    >
                                        @if ($isReorderableWithDragAndDrop && $reorderAction->isVisible())
                                            <span class="form-builder-choice-row__handle" x-sortable-handle>
                                                {{ $reorderAction }}
                                            </span>
                                        @endif

                                        <div class="form-builder-choice-row__label">
                                            @if ($labelComponent && ! $labelComponent->isHidden())
                                                {{ $labelComponent }}
                                            @endif
                                        </div>

                                        @if ($isDeletable && $itemDeleteAction->isVisible())
                                            <span class="form-builder-choice-row__delete">
                                                {{ $itemDeleteAction }}
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="form-builder-choices-drawer__empty">
                                هنوز گزینه‌ای اضافه نشده است.
                            </div>
                        @endif
                    </div>

                    @if ($isAddable && $addAction->isVisible())
                        <footer class="form-builder-choices-drawer__footer">
                            {{ $addAction }}
                        </footer>
                    @endif
                </section>
            </div>
        </template>
    </div>
</x-dynamic-component>
