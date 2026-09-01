const latinDigits = (value) => String(value ?? '')
    .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));

export const canonicalizeFormNumber = (value) => latinDigits(value)
    .trim()
    .replace(/٫/g, '.')
    .replace(/[,٬\s\u00a0\u202f]/g, '');

export const formatFormNumber = (value, thousandsSeparator = false) => {
    const canonical = canonicalizeFormNumber(value);
    const match = canonical.match(/^(-?)(\d+)(?:\.(\d*))?$/);

    if (! match) return String(value ?? '');

    const integer = thousandsSeparator
        ? match[2].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
        : match[2];
    const decimal = match[3] === undefined ? '' : `.${match[3]}`;

    return `${match[1]}${integer}${decimal}`;
};

export const initFormNumberInputs = (root = document) => {
    root.querySelectorAll('[data-form-number]').forEach((input) => {
        if (input.dataset.formNumberReady === 'true') return;

        input.dataset.formNumberReady = 'true';
        const usesThousands = input.dataset.thousandsSeparator === 'true';
        const canonicalize = () => {
            input.value = canonicalizeFormNumber(input.value);
        };
        const format = () => {
            input.value = formatFormNumber(input.value, usesThousands);
        };

        input.addEventListener('focus', canonicalize);
        input.addEventListener('blur', format);
        input.addEventListener('input', () => {
            const cursor = input.selectionStart;
            const prefix = input.value.slice(0, cursor ?? input.value.length);
            const canonicalPrefix = canonicalizeFormNumber(prefix);
            canonicalize();
            input.setSelectionRange?.(canonicalPrefix.length, canonicalPrefix.length);
        });
        input.form?.addEventListener('submit', canonicalize);
        format();
    });
};
