@php
    $contentStatePath = $contentStatePath ?? 'data.content';
    $images = collect($images ?? [])->values();
@endphp

@once
    <script>
        (() => {
            const registerMediaLibraryInserter = () => {
                if (! window.Alpine || window.Alpine.__mediaLibraryInserterRegistered) {
                    return
                }

                window.Alpine.__mediaLibraryInserterRegistered = true

                window.Alpine.data('mediaLibraryInserter', (content, images = []) => ({
                    open: false,
                    search: '',
                    content,
                    images,

                    filteredImages() {
                        const query = this.search.trim().toLowerCase()

                        if (! query) {
                            return this.images
                        }

                        return this.images.filter((image) => String(image.name || '').toLowerCase().includes(query))
                    },

                    insert(image) {
                        const url = String(image.url || '')
                        const alt = String(image.name || '').replace(/["'<>]/g, '')
                        const html = '<figure><img src=' + JSON.stringify(url) + ' alt=' + JSON.stringify(alt) + '></figure>'

                        this.content = (this.content || '') + html
                        this.open = false
                    },

                    mountToolbarActions(root) {
                        const actions = root.querySelector('[data-media-library-toolbar-actions]')

                        if (! actions) {
                            return
                        }

                        const mount = () => {
                            const toolbar = Array
                                .from(document.querySelectorAll('.fi-fo-rich-editor-toolbar'))
                                .find((candidate) => root.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING)

                            if (! toolbar) {
                                return false
                            }

                            toolbar.querySelector('[data-media-library-toolbar-actions]')?.remove()

                            const toolbarRow = toolbar.querySelector('.flex.gap-x-3.overflow-x-auto') || toolbar.firstElementChild || toolbar

                            actions.style.display = ''
                            toolbarRow.appendChild(actions)

                            return true
                        }

                        if (mount()) {
                            return
                        }

                        requestAnimationFrame(mount)
                    },
                }))
            }

            document.addEventListener('alpine:init', registerMediaLibraryInserter)
            registerMediaLibraryInserter()

            const registerResizableEditorImages = () => {
                if (window.__mediaLibraryResizableImagesRegistered) {
                    return
                }

                window.__mediaLibraryResizableImagesRegistered = true

                const handleSize = 18
                let selectedImage = null
                let overlay = null
                let lastFocusedEditor = null

                const blockSelector = 'p, h1, h2, h3, blockquote, li, div, figure'

                const removeOverlay = () => {
                    overlay?.remove()
                    overlay = null

                    if (selectedImage) {
                        selectedImage.removeAttribute('data-media-library-resize-selected')
                        selectedImage = null
                    }
                }

                const positionOverlay = () => {
                    if (! selectedImage || ! overlay || ! document.body.contains(selectedImage)) {
                        removeOverlay()

                        return
                    }

                    const rect = selectedImage.getBoundingClientRect()

                    overlay.style.left = `${rect.left + window.scrollX}px`
                    overlay.style.top = `${rect.top + window.scrollY}px`
                    overlay.style.width = `${rect.width}px`
                    overlay.style.height = `${rect.height}px`
                }

                const showOverlay = (image) => {
                    if (selectedImage === image && overlay) {
                        positionOverlay()

                        return
                    }

                    removeOverlay()

                    selectedImage = image
                    selectedImage.setAttribute('data-media-library-resize-selected', 'true')

                    overlay = document.createElement('div')
                    overlay.setAttribute('data-media-library-resize-overlay', 'true')
                    overlay.style.position = 'absolute'
                    overlay.style.pointerEvents = 'none'
                    overlay.style.zIndex = '60'
                    overlay.style.border = '1px solid rgb(37, 99, 235)'
                    overlay.style.boxSizing = 'border-box'

                    const positions = [
                        ['top', 'left'],
                        ['top', 'right'],
                        ['bottom', 'left'],
                        ['bottom', 'right'],
                    ]

                    positions.forEach(([vertical, horizontal]) => {
                        const handle = document.createElement('span')
                        handle.style.position = 'absolute'
                        handle.style.width = '10px'
                        handle.style.height = '10px'
                        handle.style.borderRadius = '999px'
                        handle.style.background = 'rgb(37, 99, 235)'
                        handle.style.border = '2px solid white'
                        handle.style.boxShadow = '0 1px 4px rgba(15, 23, 42, .35)'
                        handle.style[vertical] = '-6px'
                        handle.style[horizontal] = '-6px'

                        overlay.appendChild(handle)
                    })

                    document.body.appendChild(overlay)
                    positionOverlay()
                }

                const getActiveEditor = () => {
                    if (selectedImage?.closest?.('trix-editor')) {
                        return selectedImage.closest('trix-editor')
                    }

                    if (document.activeElement?.matches?.('trix-editor')) {
                        return document.activeElement
                    }

                    if (lastFocusedEditor && document.body.contains(lastFocusedEditor)) {
                        return lastFocusedEditor
                    }

                    return document.querySelector('trix-editor')
                }

                const getBlockElement = (node, editor) => {
                    const element = node?.nodeType === Node.TEXT_NODE ? node.parentElement : node

                    if (! element?.closest) {
                        return null
                    }

                    const block = element.closest(blockSelector)

                    if (! block || block === editor || ! editor.contains(block)) {
                        return null
                    }

                    return block
                }

                const alignImage = (image, alignment) => {
                    const figure = image.closest('figure') || image.parentElement

                    if (! figure) {
                        return
                    }

                    figure.style.textAlign = alignment
                    image.style.display = 'inline-block'
                    image.style.float = ''
                }

                const alignSelectedBlocks = (editor, alignment) => {
                    const selection = window.getSelection()
                    const blocks = new Set()

                    if (selection?.rangeCount) {
                        const range = selection.getRangeAt(0)

                        if (editor.contains(range.commonAncestorContainer)) {
                            const startBlock = getBlockElement(range.startContainer, editor)

                            if (startBlock) {
                                blocks.add(startBlock)
                            }

                            editor.querySelectorAll(blockSelector).forEach((block) => {
                                if (block === editor) {
                                    return
                                }

                                try {
                                    if (range.intersectsNode(block)) {
                                        blocks.add(block)
                                    }
                                } catch (error) {
                                    //
                                }
                            })
                        }
                    }

                    if (! blocks.size) {
                        const fallbackBlock = getBlockElement(editor, editor) || editor.querySelector(blockSelector)

                        if (fallbackBlock) {
                            blocks.add(fallbackBlock)
                        }
                    }

                    blocks.forEach((block) => {
                        block.style.textAlign = alignment
                    })
                }

                const align = (alignment) => {
                    if (! ['left', 'center', 'right'].includes(alignment)) {
                        return
                    }

                    const editor = getActiveEditor()

                    if (! editor) {
                        return
                    }

                    if (selectedImage && editor.contains(selectedImage)) {
                        alignImage(selectedImage, alignment)
                    } else {
                        alignSelectedBlocks(editor, alignment)
                    }

                    syncEditorContent(editor)
                    positionOverlay()
                }

                window.addEventListener('media-library-align', (event) => {
                    align(event.detail?.alignment)
                })

                const getEditorImage = (target) => {
                    if (! target?.matches?.('trix-editor img')) {
                        return null
                    }

                    return target
                }

                const isInResizeCorner = (event, image) => {
                    const rect = image.getBoundingClientRect()

                    return event.clientX >= rect.right - handleSize && event.clientY >= rect.bottom - handleSize
                }

                const syncEditorContent = (editor) => {
                    const input = document.getElementById(editor.getAttribute('input'))

                    if (! input) {
                        return
                    }

                    input.value = editor.innerHTML
                    input.dispatchEvent(new Event('input', { bubbles: true }))
                    input.dispatchEvent(new Event('change', { bubbles: true }))
                    editor.dispatchEvent(new CustomEvent('trix-change', { bubbles: true }))
                }

                document.addEventListener('mousemove', (event) => {
                    const image = getEditorImage(event.target)

                    if (! image) {
                        return
                    }

                    image.style.cursor = isInResizeCorner(event, image) ? 'nwse-resize' : ''
                })

                document.addEventListener('focusin', (event) => {
                    if (event.target?.matches?.('trix-editor')) {
                        lastFocusedEditor = event.target
                    }
                })

                document.addEventListener('click', (event) => {
                    const image = getEditorImage(event.target)

                    if (image) {
                        showOverlay(image)

                        return
                    }

                    if (! event.target?.closest?.('trix-editor')) {
                        removeOverlay()
                    }
                })

                document.addEventListener('mousedown', (event) => {
                    const image = getEditorImage(event.target)

                    if (! image || ! isInResizeCorner(event, image)) {
                        return
                    }

                    const editor = image.closest('trix-editor')

                    if (! editor) {
                        return
                    }

                    event.preventDefault()
                    event.stopPropagation()

                    const editorWidth = editor.getBoundingClientRect().width
                    const startX = event.clientX
                    const startWidth = image.getBoundingClientRect().width
                    const minWidth = 80
                    const maxWidth = Math.max(minWidth, editorWidth)

                    image.style.height = 'auto'
                    showOverlay(image)

                    const resize = (moveEvent) => {
                        const width = Math.min(maxWidth, Math.max(minWidth, startWidth + moveEvent.clientX - startX))
                        const roundedWidth = Math.round(width)

                        image.style.width = `${roundedWidth}px`
                        image.setAttribute('width', String(roundedWidth))
                        positionOverlay()
                        syncEditorContent(editor)
                    }

                    const stop = () => {
                        document.removeEventListener('mousemove', resize)
                        document.removeEventListener('mouseup', stop)
                        syncEditorContent(editor)
                    }

                    document.addEventListener('mousemove', resize)
                    document.addEventListener('mouseup', stop)
                })

                window.addEventListener('scroll', positionOverlay, true)
                window.addEventListener('resize', positionOverlay)
            }

            registerResizableEditorImages()
        })()
    </script>
@endonce

<div
    x-data='mediaLibraryInserter($wire.entangle({{ \Illuminate\Support\Js::encode($contentStatePath) }}), {{ \Illuminate\Support\Js::encode($images) }})'
    x-init="mountToolbarActions($el)"
    x-on:keydown.escape.window="open = false"
    x-on:media-library-open.window="open = true"
>
    <div
        data-media-library-toolbar-actions
        class="contents"
        style="display: none"
        x-data="{
            align(alignment) {
                window.dispatchEvent(new CustomEvent('media-library-align', {
                    detail: { alignment },
                }))
            },
            openMediaLibrary() {
                window.dispatchEvent(new CustomEvent('media-library-open'))
            },
        }"
    >
        <x-filament-forms::rich-editor.toolbar.group data-trix-button-group="media-library-tools">
            <x-filament-forms::rich-editor.toolbar.button
                x-on:click.prevent="align('left')"
                title="چینش چپ"
                aria-label="چینش چپ"
                tabindex="-1"
            >
                <x-filament::icon icon="heroicon-m-bars-3-bottom-left" class="h-5 w-5" />
            </x-filament-forms::rich-editor.toolbar.button>

            <x-filament-forms::rich-editor.toolbar.button
                x-on:click.prevent="align('center')"
                title="چینش وسط"
                aria-label="چینش وسط"
                tabindex="-1"
            >
                <x-filament::icon icon="heroicon-m-bars-3" class="h-5 w-5" />
            </x-filament-forms::rich-editor.toolbar.button>

            <x-filament-forms::rich-editor.toolbar.button
                x-on:click.prevent="align('right')"
                title="چینش راست"
                aria-label="چینش راست"
                tabindex="-1"
            >
                <x-filament::icon icon="heroicon-m-bars-3-bottom-right" class="h-5 w-5" />
            </x-filament-forms::rich-editor.toolbar.button>

            <x-filament-forms::rich-editor.toolbar.button
                x-on:click.prevent="openMediaLibrary()"
                title="افزودن تصویر از کتابخانه رسانه"
                aria-label="افزودن تصویر از کتابخانه رسانه"
                tabindex="-1"
            >
                <x-filament::icon icon="heroicon-m-photo" class="h-5 w-5" />
            </x-filament-forms::rich-editor.toolbar.button>
        </x-filament-forms::rich-editor.toolbar.group>
    </div>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        style="display: none; background-color: rgba(2, 6, 23, 0.78); backdrop-filter: blur(2px)"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-gray-950/60 p-4"
    >
        <div
            x-on:click.outside="open = false"
            style="height: calc(100dvh - 2rem); max-height: calc(100dvh - 2rem)"
            class="flex h-[calc(100dvh-2rem)] max-h-[calc(100dvh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
        >
            <div style="padding: 1rem 1.25rem" class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">افزودن تصویر</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">یک تصویر را در کتابخانه رسانه جست‌وجو و انتخاب کنید.</p>
                </div>

                <button
                    type="button"
                    x-on:click="open = false"
                    class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-white"
                    aria-label="بستن"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div style="padding: 1rem 1.25rem" class="border-b border-gray-200 p-5 dark:border-gray-800">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        x-model="search"
                        placeholder="جست‌وجوی تصاویر..."
                    />
                </x-filament::input.wrapper>
            </div>

            <div style="padding: 1.25rem" class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <template x-if="images.length === 0">
                    <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        تصویر قابل استفاده‌ای در کتابخانه رسانه پیدا نشد.
                    </div>
                </template>

                <template x-if="images.length > 0 && filteredImages().length === 0">
                    <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        تصویری مطابق جست‌وجوی شما پیدا نشد.
                    </div>
                </template>

                <div class="grid gap-3" style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.75rem">
                    <template x-for="image in filteredImages()" x-bind:key="image.id">
                        <button
                            type="button"
                            x-on:click="insert(image)"
                            style="min-width: 0; width: 100%; overflow: hidden"
                            class="overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-sm transition hover:border-primary-500 hover:ring-2 hover:ring-primary-500/30 dark:border-gray-800 dark:bg-gray-950"
                        >
                            <img
                                x-bind:src="image.url"
                                x-bind:alt="image.name"
                                style="display: block; width: 100%; height: 9rem; object-fit: cover"
                                class="w-full object-cover"
                            >
                            <span class="block truncate px-3 py-2 text-sm text-gray-700 dark:text-gray-200" x-text="image.name"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
