import {
    formatJalali,
    gregorianToJalali,
    jalaliMonthLength,
    jalaliToGregorian,
    persianDigits,
} from './jalali-date';

const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const weekdayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
const globalFloor = { year: 1350, month: 1, day: 1 };
const globalFloorIso = jalaliToGregorian(globalFloor.year, globalFloor.month, globalFloor.day);
let activePicker = null;

const localIsoToday = () => {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
};

const initializePicker = (root) => {
    if (root.dataset.datePickerInitialized === 'true') return;

    const canonical = root.querySelector('[data-form-date-canonical]');
    const trigger = root.querySelector('[data-form-date-trigger]');
    const display = root.querySelector('[data-form-date-display]');
    const calendar = root.querySelector('[data-form-date-calendar]');
    if (! canonical || ! trigger || ! display || ! calendar) return;

    root.dataset.datePickerInitialized = 'true';
    canonical.classList.add('is-enhanced');
    trigger.hidden = false;

    const initial = gregorianToJalali(canonical.value) || gregorianToJalali(localIsoToday());
    const minimum = gregorianToJalali(root.dataset.minDate) || globalFloor;
    const configuredMaximum = gregorianToJalali(root.dataset.maxDate);
    const practicalMaximumYear = Math.max(initial.year, gregorianToJalali(localIsoToday()).year) + 50;
    const maximum = configuredMaximum || { year: practicalMaximumYear, month: 12, day: 29 };
    let viewYear = initial.year;
    let viewMonth = initial.month;

    const syncDisplay = () => {
        const selected = gregorianToJalali(canonical.value);
        display.textContent = selected ? formatJalali(selected) : root.dataset.placeholder;
        trigger.classList.toggle('is-placeholder', ! selected);
    };

    const isAllowed = (iso) => iso
        && iso >= globalFloorIso
        && (! root.dataset.minDate || iso >= root.dataset.minDate)
        && (! root.dataset.maxDate || iso <= root.dataset.maxDate);

    const monthKey = (year, month) => (year * 100) + month;
    const canViewMonth = (year, month) => monthKey(year, month) >= monthKey(minimum.year, minimum.month)
        && monthKey(year, month) <= monthKey(maximum.year, maximum.month);

    const close = (restoreFocus = false) => {
        calendar.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (activePicker === root) activePicker = null;
        if (restoreFocus) trigger.focus();
    };

    const positionCalendar = () => {
        calendar.style.removeProperty('transform');
        const bounds = calendar.getBoundingClientRect();
        const gutter = 16;
        let shift = 0;
        if (bounds.left < gutter) shift = gutter - bounds.left;
        if (bounds.right > window.innerWidth - gutter) shift = (window.innerWidth - gutter) - bounds.right;
        if (shift !== 0) calendar.style.transform = `translateX(${shift}px)`;
    };

    const render = () => {
        calendar.replaceChildren();

        const header = document.createElement('div');
        header.className = 'form-date-picker__header';
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.setAttribute('aria-label', 'ماه قبل');
        previous.textContent = '‹';
        const selectors = document.createElement('div');
        selectors.className = 'form-date-picker__selectors';
        const monthSelect = document.createElement('select');
        monthSelect.setAttribute('aria-label', 'انتخاب ماه');
        monthNames.forEach((name, index) => {
            const option = document.createElement('option');
            option.value = String(index + 1);
            option.textContent = name;
            option.selected = viewMonth === index + 1;
            option.disabled = ! canViewMonth(viewYear, index + 1);
            monthSelect.append(option);
        });
        const yearSelect = document.createElement('select');
        yearSelect.setAttribute('aria-label', 'انتخاب سال');
        for (let year = minimum.year; year <= maximum.year; year += 1) {
            const option = document.createElement('option');
            option.value = String(year);
            option.textContent = persianDigits(year);
            option.selected = viewYear === year;
            yearSelect.append(option);
        }
        selectors.append(monthSelect, yearSelect);
        const next = document.createElement('button');
        next.type = 'button';
        next.setAttribute('aria-label', 'ماه بعد');
        next.textContent = '›';
        const previousMonth = viewMonth === 1 ? { year: viewYear - 1, month: 12 } : { year: viewYear, month: viewMonth - 1 };
        const nextMonth = viewMonth === 12 ? { year: viewYear + 1, month: 1 } : { year: viewYear, month: viewMonth + 1 };
        previous.disabled = ! canViewMonth(previousMonth.year, previousMonth.month);
        next.disabled = ! canViewMonth(nextMonth.year, nextMonth.month);
        header.append(previous, selectors, next);

        const grid = document.createElement('div');
        grid.className = 'form-date-picker__grid';
        grid.setAttribute('role', 'grid');
        weekdayNames.forEach((name) => {
            const weekday = document.createElement('span');
            weekday.className = 'form-date-picker__weekday';
            weekday.textContent = name;
            grid.append(weekday);
        });

        const firstIso = jalaliToGregorian(viewYear, viewMonth, 1);
        const firstWeekday = firstIso ? (new Date(`${firstIso}T12:00:00Z`).getUTCDay() + 1) % 7 : 0;
        for (let blank = 0; blank < firstWeekday; blank += 1) grid.append(document.createElement('span'));

        const selectedIso = canonical.value;
        for (let day = 1; day <= jalaliMonthLength(viewYear, viewMonth); day += 1) {
            const iso = jalaliToGregorian(viewYear, viewMonth, day);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'form-date-picker__day';
            button.textContent = persianDigits(day);
            button.dataset.date = iso || '';
            button.disabled = ! isAllowed(iso);
            button.setAttribute('aria-label', `${persianDigits(day)} ${monthNames[viewMonth - 1]} ${persianDigits(viewYear)}`);
            button.setAttribute('aria-selected', iso === selectedIso ? 'true' : 'false');
            if (iso === localIsoToday()) button.classList.add('is-today');
            grid.append(button);
        }

        const footer = document.createElement('div');
        footer.className = 'form-date-picker__footer';
        if (! canonical.required) {
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.dataset.clearDate = 'true';
            clear.textContent = 'پاک کردن تاریخ';
            footer.append(clear);
        }

        calendar.append(header, grid, footer);

        previous.addEventListener('click', () => {
            if (previous.disabled) return;
            viewMonth -= 1;
            if (viewMonth === 0) { viewMonth = 12; viewYear -= 1; }
            render();
            calendar.querySelector('[aria-label="ماه قبل"]')?.focus();
        });
        next.addEventListener('click', () => {
            if (next.disabled) return;
            viewMonth += 1;
            if (viewMonth === 13) { viewMonth = 1; viewYear += 1; }
            render();
            calendar.querySelector('[aria-label="ماه بعد"]')?.focus();
        });
        monthSelect.addEventListener('change', () => {
            viewMonth = Number.parseInt(monthSelect.value, 10);
            render();
            calendar.querySelector('[aria-label="انتخاب ماه"]')?.focus();
        });
        yearSelect.addEventListener('change', () => {
            viewYear = Number.parseInt(yearSelect.value, 10);
            if (! canViewMonth(viewYear, viewMonth)) {
                viewMonth = viewYear === minimum.year ? minimum.month : maximum.month;
            }
            render();
            calendar.querySelector('[aria-label="انتخاب سال"]')?.focus();
        });
    };

    const open = () => {
        if (activePicker && activePicker !== root) {
            activePicker.dispatchEvent(new CustomEvent('form-date-picker:close'));
        }
        activePicker = root;
        const selected = gregorianToJalali(canonical.value);
        if (selected) { viewYear = selected.year; viewMonth = selected.month; }
        render();
        calendar.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        positionCalendar();
        calendar.querySelector('.form-date-picker__day:not(:disabled)')?.focus();
    };

    trigger.addEventListener('click', () => calendar.hidden ? open() : close(true));
    canonical.addEventListener('invalid', () => trigger.focus());
    calendar.addEventListener('click', (event) => {
        const day = event.target.closest('[data-date]');
        if (day && ! day.disabled && day.dataset.date) {
            canonical.value = day.dataset.date;
            canonical.dispatchEvent(new Event('change', { bubbles: true }));
            syncDisplay();
            close(true);
            return;
        }
        if (event.target.closest('[data-clear-date]')) {
            canonical.value = '';
            canonical.dispatchEvent(new Event('change', { bubbles: true }));
            syncDisplay();
            close(true);
        }
    });
    root.addEventListener('form-date-picker:close', () => close());
    document.addEventListener('click', (event) => {
        if (! event.composedPath().includes(root)) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! calendar.hidden) close(true);
    });
    window.addEventListener('resize', () => {
        if (! calendar.hidden) positionCalendar();
    });

    syncDisplay();
};

export const initFormDatePickers = () => {
    document.querySelectorAll('[data-form-date-picker]').forEach(initializePicker);
};
