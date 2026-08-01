<script>
    (() => {
        if (window.__formActionModalInitialized) {
            return
        }

        window.__formActionModalInitialized = true

        let overlay = null
        let controller = null
        let returnFocus = null

        const close = () => {
            controller?.abort()
            controller = null
            overlay?.remove()
            overlay = null
            document.body.classList.remove('form-action-modal-open')
            returnFocus?.focus()
            returnFocus = null
        }

        const open = async (form) => {
            close()
            returnFocus = form.querySelector('button[type="submit"]')
            controller = new AbortController()
            overlay = document.createElement('div')
            overlay.className = 'form-action-modal'
            overlay.setAttribute('role', 'dialog')
            overlay.setAttribute('aria-modal', 'true')
            overlay.setAttribute('aria-labelledby', 'form-action-modal-title')
            overlay.innerHTML = '<div class="form-action-modal__panel" aria-busy="true"><p class="form-action-modal__loading">در حال بارگذاری فرم…</p></div>'
            document.body.appendChild(overlay)
            document.body.classList.add('form-action-modal-open')

            try {
                const response = await fetch(form.dataset.formActionModalUrl, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'text/html' },
                    signal: controller.signal,
                })

                if (! response.ok) {
                    throw new Error('Unable to load form')
                }

                const panel = overlay?.querySelector('.form-action-modal__panel')

                if (! panel) {
                    return
                }

                panel.innerHTML = await response.text()
                panel.setAttribute('aria-busy', 'false')
                document.dispatchEvent(new CustomEvent('forms:rendered'))
                panel.querySelector('input:not([type="hidden"]), textarea, button')?.focus()
            } catch (error) {
                if (error.name !== 'AbortError') {
                    form.submit()
                }
            }
        }

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-form-action-modal-url]')

            if (form) {
                event.preventDefault()
                open(form)
            }
        })

        document.addEventListener('click', (event) => {
            if (overlay && (event.target === overlay || event.target.closest('[data-form-action-modal-close]'))) {
                close()
            }
        })

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && overlay) {
                close()
            }
        })
    })()
</script>
