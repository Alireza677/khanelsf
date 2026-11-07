@once
<script>
(() => {
    document.querySelector('[data-order-errors]')?.focus();
    document.querySelectorAll('[data-copy-order-code]').forEach(button => {
        button.addEventListener('click', async () => {
            const container = button.closest('[data-order-copy]');
            const input = container.querySelector('[data-order-code]');
            const feedback = container.querySelector('[data-copy-feedback]');
            button.disabled = true;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('clipboard-unavailable');
                await navigator.clipboard.writeText(input.value);
                feedback.textContent = 'کد سفارش کپی شد.';
                button.querySelector('[data-copy-label]').textContent = 'کپی شد';
            } catch {
                input.focus();
                input.select();
                feedback.textContent = 'کپی خودکار ممکن نشد؛ کد انتخاب شد. از گزینه کپی دستگاه استفاده کنید.';
            } finally {
                button.disabled = false;
            }
        });
    });
    document.querySelectorAll('[data-order-payment-form]').forEach(form => {
        const button = form.querySelector('button');
        button.dataset.idleLabel = button.textContent;
        form.addEventListener('submit', () => {
            document.querySelectorAll('[data-order-payment-form] button').forEach(button => button.disabled = true);
            button.textContent = 'در حال بررسی…';
            form.setAttribute('aria-busy', 'true');
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-order-payment-form]').forEach(form => {
            form.removeAttribute('aria-busy');
            const button = form.querySelector('button');
            button.disabled = false;
            button.textContent = button.dataset.idleLabel;
        });
    });
})();
</script>
@endonce
