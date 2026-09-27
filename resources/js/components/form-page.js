// Fit the standalone experience below any site-header template without fixed header assumptions.
const fitPageViewport = (page) => {
    if (! page || page.dataset.viewportReady === 'true') return;
    page.dataset.viewportReady = 'true';
    const update = () => {
        const top = Math.max(0, page.getBoundingClientRect().top + window.scrollY);
        page.style.setProperty('--form-page-viewport-space', `${Math.max(0, window.innerHeight - top)}px`);
    };
    update();
    window.addEventListener('resize', update, { passive: true });
    window.addEventListener('pageshow', update);
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(update);
        // Header height may change after fonts/images load or a sticky header collapses.
        for (let sibling = page.closest('main')?.previousElementSibling; sibling; sibling = sibling.previousElementSibling) {
            if (! sibling.matches('script, style, link')) observer.observe(sibling);
        }
    }
};

// Standalone presentation only. All panels stay mounted so answers/files survive navigation.
export const initFormPages = (root = document) => {
    root.querySelectorAll('[data-form-page]').forEach((form) => {
        if (form.dataset.pageReady === 'true') return;
        const steps = Array.from(form.querySelectorAll('[data-form-step]'));
        if (! steps.length) return;
        form.dataset.pageReady = 'true';
        form.noValidate = true;
        fitPageViewport(form.closest('.form-page'));

        const links = Array.from(form.querySelectorAll('[data-page-step-link]'));
        const next = form.querySelector('[data-page-next]');
        const back = form.querySelector('[data-page-back]');
        const submit = form.querySelector('[data-page-submit]');
        const confirmation = form.querySelector('[data-submit-confirmation]');
        const result = form.parentElement.querySelector('[data-calculator-result-modal]');
        const resultLink = form.querySelector('[data-page-result-link]');
        let current = 0;
        let resultStale = false;

        const controls = (field) => Array.from(field.querySelectorAll('input, select, textarea')).filter((input) => ! input.disabled);
        const answered = (field) => controls(field).some((input) => ['checkbox', 'radio'].includes(input.type)
            ? input.checked : input.type === 'file' ? input.files.length > 0 : input.value.trim() !== '');
        const invalidControl = (field) => controls(field).find((input) => ! input.validity.valid);
        const complete = (step) => {
            const fields = Array.from(step.querySelectorAll('[data-page-field]'));
            return fields.some(answered) && fields.every((field) => ! invalidControl(field)
                && (field.dataset.fieldRequired !== 'true' || answered(field)))
                && ! step.querySelector('.form-error:not([hidden])');
        };
        const updateStepper = () => {
            links.forEach((link, index) => {
                const state = current === index ? 'current' : complete(steps[index]) ? 'completed' : 'unanswered';
                const label = { current: 'مرحله جاری', completed: 'تکمیل شده', unanswered: 'بدون پاسخ' }[state];
                link.dataset.state = state;
                link.querySelector('[data-page-step-state]').textContent = label;
                link.setAttribute('aria-label', `مرحله ${(index + 1).toLocaleString('fa-IR')}: ${link.dataset.stepTitle}، ${label}`);
                if (state === 'current') link.setAttribute('aria-current', 'step');
                else link.removeAttribute('aria-current');
            });
            if (resultLink) {
                resultLink.disabled = ! result || resultStale;
                resultLink.dataset.state = result && ! resultStale ? 'completed' : 'unanswered';
            }
        };
        const show = (index, focus = true) => {
            current = index;
            steps.forEach((step, stepIndex) => {
                step.hidden = stepIndex !== current;
                step.setAttribute('aria-hidden', step.hidden ? 'true' : 'false');
            });
            if (next) next.hidden = current >= steps.length - 1;
            if (back) { back.hidden = false; back.disabled = current === 0; }
            submit.hidden = current !== steps.length - 1;
            if (confirmation) confirmation.hidden = current !== steps.length - 1;
            updateStepper();
            if (focus) {
                const heading = steps[current]?.querySelector('h2');
                if (heading) {
                    heading.tabIndex = -1;
                    heading.focus({ preventScroll: true });
                    // Keep the compact desktop shell in place while revealing the question.
                    heading.scrollIntoView({ block: 'nearest', behavior: 'auto' });
                }
            }
        };
        const focusField = (field) => {
            if (! field) return;
            const target = field.querySelector('[data-form-select-trigger], [data-form-date-trigger]:not([hidden])')
                || field.querySelector('input:not([type="hidden"]), select, textarea');
            target?.focus();
        };
        const clearClientError = (field) => {
            const error = field.querySelector('[data-page-validation-error]');
            if (! error) return;
            field.querySelectorAll('[data-page-invalid]').forEach((input) => {
                const ids = (input.getAttribute('aria-describedby') || '').split(' ').filter((id) => id && id !== error.id);
                if (ids.length) input.setAttribute('aria-describedby', ids.join(' '));
                else input.removeAttribute('aria-describedby');
                input.removeAttribute('aria-invalid');
                delete input.dataset.pageInvalid;
            });
            error.remove();
        };
        links.forEach((link) => link.addEventListener('click', () => show(Number(link.dataset.pageStepLink))));
        next?.addEventListener('click', () => show(Math.min(current + 1, steps.length - 1)));
        back?.addEventListener('click', () => show(Math.max(current - 1, 0)));
        resultLink?.addEventListener('click', () => {
            if (result && ! resultStale) result.dispatchEvent(new CustomEvent('calculator-result:open', { detail: { opener: resultLink } }));
        });
        result?.addEventListener('calculator-result:expired', () => { resultStale = true; updateStepper(); });

        const onAnswer = (event) => {
            const field = event.target.closest('[data-page-field]');
            if (! field) return;
            clearClientError(field);
            field.querySelectorAll('.form-error').forEach((error) => { error.hidden = true; });
            field.querySelectorAll('[aria-invalid="true"]').forEach((input) => input.removeAttribute('aria-invalid'));
            // A saved result belongs to the submitted answers, never to later edits.
            resultStale = true;
            if (result) result.dataset.resultStale = 'true';
            updateStepper();
        };
        form.addEventListener('input', onAnswer);
        form.addEventListener('change', onAnswer);
        form.addEventListener('submit', (event) => {
            let firstInvalid = null;
            steps.forEach((step, index) => step.querySelectorAll('[data-page-field]').forEach((field) => {
                clearClientError(field);
                const invalid = invalidControl(field);
                const missing = field.dataset.fieldRequired === 'true' && ! answered(field);
                if (! invalid && ! missing) return;
                const error = document.createElement('p');
                error.className = 'form-error';
                error.dataset.pageValidationError = '';
                error.id = `${controls(field)[0].id}-page-error`;
                error.setAttribute('role', 'alert');
                error.textContent = missing ? 'پاسخ به این سؤال الزامی است.' : 'لطفاً مقدار این فیلد را بررسی و اصلاح کنید.';
                field.append(error);
                field.querySelectorAll('input, select, textarea, [data-form-select-trigger], [data-form-date-trigger]').forEach((input) => {
                    input.dataset.pageInvalid = 'true';
                    input.setAttribute('aria-invalid', 'true');
                    input.setAttribute('aria-describedby', `${input.getAttribute('aria-describedby') || ''} ${error.id}`.trim());
                });
                firstInvalid ??= { index, field };
            }));
            if (firstInvalid) {
                event.preventDefault();
                show(firstInvalid.index);
                focusField(firstInvalid.field);
            }
        });

        const invalidStep = steps.findIndex((step) => step.querySelector('.form-error'));
        show(invalidStep >= 0 ? invalidStep : form.dataset.submitConfirmationError === 'true'
            ? steps.length - 1 : result || form.dataset.initialStep === 'last' ? steps.length - 1 : 0, false);
        if (invalidStep >= 0) focusField(steps[invalidStep].querySelector('.form-error').closest('[data-page-field]'));
    });
};
