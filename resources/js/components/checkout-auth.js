export const checkoutDestination = (destination, checkoutUrl) => {
    if (typeof destination !== 'string' || /[\\\x00-\x20\x7f]/.test(destination)) return null;
    try {
        const expected = new URL(checkoutUrl);
        const target = new URL(destination, expected);
        return target.origin === expected.origin && target.pathname === expected.pathname
            && ! target.search && ! target.hash && ! target.username && ! target.password ? target.href : null;
    } catch {
        return null;
    }
};

// Status-based transport: an HTML redirect/200 is never treated as successful authentication.
export const sendClientAuth = async (action, data, signal, fetcher = fetch) => {
    const response = await fetcher(action, {
        method: 'POST', body: data, credentials: 'same-origin', redirect: 'error', signal,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = response.headers.get('content-type')?.includes('application/json')
        ? await response.json() : {};
    if (response.ok && json.authenticated === true) return { kind: 'success', destination: json.redirect_url };
    if (response.status === 422) return { kind: 'validation', errors: json.errors || {} };
    if (response.status === 419) return { kind: 'expired', message: 'نشست فرم منقضی شده است. صفحه ورود را دوباره باز کنید.' };
    if (response.status === 429) return {
        kind: 'limited', retryAfter: Math.min(300, Math.max(1, Number(response.headers.get('retry-after')) || 60)),
        message: 'تعداد تلاش‌ها زیاد است. کمی صبر کنید و دوباره تلاش کنید.',
    };
    return { kind: 'error', message: 'ورود انجام نشد. دوباره تلاش کنید یا صفحه ورود را باز کنید.' };
};

export const initCheckoutAuth = () => {
    const modal = document.querySelector('[data-checkout-auth]');
    if (! modal || modal.dataset.authReady === 'true' || ! window.fetch) return;
    modal.dataset.authReady = 'true';
    const forms = Array.from(modal.querySelectorAll('[data-public-auth-form]'));
    const summary = modal.querySelector('[data-auth-errors]');
    const status = modal.querySelector('[data-auth-status]');
    const fallback = modal.querySelector('[data-auth-fallback]');
    const sizeToViewport = () => {
        if (! window.visualViewport) return;
        modal.style.setProperty('--auth-viewport-height', window.visualViewport.height + 'px');
        modal.style.setProperty('--auth-viewport-top', window.visualViewport.offsetTop + 'px');
    };
    sizeToViewport();
    window.visualViewport?.addEventListener('resize', sizeToViewport);
    window.visualViewport?.addEventListener('scroll', sizeToViewport);
    let pending = false;
    let expired = false;
    let controller = null;
    let cooldown = null;

    const clearPasswords = () => modal.querySelectorAll('input[type="password"]').forEach((input) => { input.value = ''; });
    const clearErrors = () => {
        summary.replaceChildren();
        summary.hidden = true;
        modal.querySelectorAll('[aria-invalid]').forEach((input) => input.removeAttribute('aria-invalid'));
        modal.querySelectorAll('[data-auth-field-error]').forEach((error) => { error.textContent = ''; error.hidden = true; });
    };
    const showError = (message) => {
        summary.textContent = message;
        summary.hidden = false;
        summary.focus();
    };
    const setBusy = (busy) => {
        pending = busy;
        forms.forEach((form) => {
            form.setAttribute('aria-busy', String(busy));
            form.querySelector('[data-auth-submit]').disabled = busy || expired || cooldown !== null;
        });
        modal.querySelectorAll('[data-auth-mode]').forEach((link) => link.setAttribute('aria-disabled', String(busy)));
    };
    const switchMode = (mode, focus = true) => {
        if (pending) return;
        const previousMobile = forms.find((form) => ! form.hidden)?.elements.namedItem('mobile')?.value;
        forms.forEach((form) => {
            form.hidden = form.dataset.publicAuthForm !== mode;
            const mobile = form.elements.namedItem('mobile');
            if (! mobile.value && previousMobile) mobile.value = previousMobile;
        });
        modal.querySelectorAll('[data-auth-mode]').forEach((link) => {
            if (link.dataset.authMode === mode) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        fallback.href = modal.querySelector(`[data-auth-mode="${mode}"]`).href;
        modal.querySelector('#checkout-auth-title').textContent = mode === 'register' ? 'حساب کاربری بسازید' : 'وارد حساب خود شوید';
        clearPasswords();
        clearErrors();
        status.textContent = '';
        if (cooldown !== null) showError('تعداد تلاش‌ها زیاد است. کمی صبر کنید و دوباره تلاش کنید.');
        if (focus) forms.find((form) => ! form.hidden)?.querySelector('input:not([type="hidden"])')?.focus();
    };

    // Capture all real checkout links, including Template/CMS links and the cart drawer.
    // Modified clicks retain normal navigation and the server's page-login fallback.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (! link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || link.target === '_blank' || link.hasAttribute('download')) return;
        if (! checkoutDestination(link.href, modal.dataset.checkoutUrl)) return;
        event.preventDefault();
        link.setAttribute('aria-haspopup', 'dialog');
        document.dispatchEvent(new CustomEvent('header-overlay:open', { detail: { id: modal.id, trigger: link } }));
    });

    modal.addEventListener('click', (event) => {
        const mode = event.target.closest('[data-auth-mode]');
        if (! mode) return;
        // An expired CSRF token requires a fresh page, never an automatic credential retry.
        if (expired) return;
        event.preventDefault();
        switchMode(mode.dataset.authMode);
    });
    modal.addEventListener('header-overlay:closed', () => {
        controller?.abort();
        controller = null;
        clearPasswords();
        if (! expired && cooldown === null) clearErrors();
        status.textContent = '';
        setBusy(false);
    });

    forms.forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending || expired || cooldown !== null || form.hidden) return;
        clearErrors();
        setBusy(true);
        status.textContent = 'در حال بررسی اطلاعات…';
        const requestController = new AbortController();
        controller = requestController;
        let continuing = false;
        try {
            const result = await sendClientAuth(form.action, new FormData(form), requestController.signal);
            // Closing/reopening a modal must not let an older request mutate its new state.
            if (controller !== requestController) return;
            if (result.kind === 'success') {
                const destination = checkoutDestination(result.destination, modal.dataset.checkoutUrl);
                if (! destination) throw new Error('Invalid continuation');
                clearPasswords();
                status.textContent = 'ورود انجام شد. در حال بازگشت به تسویه حساب…';
                continuing = true;
                window.location.assign(destination);
                return;
            }
            status.textContent = '';
            if (result.kind === 'validation') {
                const intro = document.createElement('p');
                intro.textContent = 'اطلاعات زیر را بررسی کنید:';
                summary.append(intro);
                const list = document.createElement('ul');
                Object.entries(result.errors).forEach(([field, messages]) => {
                    const input = form.elements.namedItem(field);
                    const message = Array.isArray(messages) ? messages[0] : String(messages);
                    if (input?.id) {
                        input.setAttribute('aria-invalid', 'true');
                        const fieldError = document.getElementById(input.id + '-error');
                        fieldError.textContent = message;
                        fieldError.hidden = false;
                        const item = document.createElement('li');
                        const link = document.createElement('a');
                        link.href = '#' + input.id;
                        link.textContent = message;
                        link.addEventListener('click', (click) => { click.preventDefault(); input.focus(); });
                        item.append(link);
                        list.append(item);
                    }
                });
                if (! list.children.length) showError('اطلاعات فرم معتبر نیست. فیلدها را بررسی کنید.');
                else {
                    summary.append(list);
                    summary.hidden = false;
                    summary.focus();
                }
            } else {
                if (result.kind === 'expired') {
                    expired = true;
                    clearPasswords();
                }
                if (result.kind === 'limited') {
                    cooldown = window.setTimeout(() => { cooldown = null; setBusy(pending); }, result.retryAfter * 1000);
                }
                showError(result.message);
            }
        } catch (error) {
            if (controller === requestController && error.name !== 'AbortError') {
                status.textContent = '';
                showError('ارتباط برقرار نشد. دوباره تلاش کنید یا صفحه ورود را باز کنید.');
            }
        } finally {
            if (controller === requestController) {
                controller = null;
                if (! continuing) setBusy(false);
            }
        }
    }));
};
