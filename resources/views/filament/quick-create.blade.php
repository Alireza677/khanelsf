@if ($items !== [])
    <div
        class="relative"
        dir="rtl"
        x-data="{
            open: false,
            desktopHover: false,
            hoverTimer: null,
            openMenu(focusFirst = false) {
                this.open = true

                if (focusFirst) {
                    this.$nextTick(() => this.menuItems()[0]?.focus())
                }
            },
            closeMenu(restoreFocus = false) {
                if (! this.open) return

                this.open = false

                if (restoreFocus) {
                    this.$nextTick(() => this.$refs.trigger.focus())
                }
            },
            menuItems() {
                return [...this.$refs.menu.querySelectorAll('[role=menuitem]')]
            },
            moveFocus(event, direction) {
                const items = this.menuItems()
                const current = items.indexOf(document.activeElement)
                const next = direction === 'first'
                    ? 0
                    : direction === 'last'
                        ? items.length - 1
                        : (current + direction + items.length) % items.length

                event.preventDefault()
                items[next]?.focus()
            },
        }"
        x-init="desktopHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches"
        x-on:mouseenter="if (desktopHover) { clearTimeout(hoverTimer); openMenu() }"
        x-on:mouseleave="if (desktopHover) hoverTimer = setTimeout(() => closeMenu(), 150)"
        x-on:keydown.escape.window="closeMenu(true)"
        x-on:click.outside="closeMenu()"
    >
        <x-filament::button
            color="primary"
            icon="heroicon-o-plus"
            size="sm"
            type="button"
            x-ref="trigger"
            x-bind:aria-expanded="open.toString()"
            aria-haspopup="menu"
            aria-controls="admin-quick-create-menu"
            x-on:click="open = ! open"
            x-on:keydown.arrow-down.prevent="openMenu(true)"
            x-on:keydown.enter.prevent="openMenu(true)"
            x-on:keydown.space.prevent="openMenu(true)"
        >
            ایجاد
        </x-filament::button>

        <div
            id="admin-quick-create-menu"
            x-cloak
            x-show="open"
            x-ref="menu"
            role="menu"
            aria-label="ایجاد محتوای جدید"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-on:keydown.arrow-down="moveFocus($event, 1)"
            x-on:keydown.arrow-up="moveFocus($event, -1)"
            x-on:keydown.home="moveFocus($event, 'first')"
            x-on:keydown.end="moveFocus($event, 'last')"
            x-on:keydown.tab="closeMenu()"
            class="absolute end-0 top-full z-50 mt-2 w-56 origin-top-right rounded-lg bg-white p-1 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            @foreach ($items as $item)
                <x-filament::dropdown.list.item
                    :href="$item['url']"
                    :icon="$item['icon']"
                    tag="a"
                    role="menuitem"
                    tabindex="-1"
                >
                    {{ $item['label'] }}
                </x-filament::dropdown.list.item>
            @endforeach
        </div>
    </div>
@endif
