const formatter = new Intl.DateTimeFormat('en-u-ca-persian', {
    timeZone: 'UTC',
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
});

const parts = (date) => Object.fromEntries(
    formatter.formatToParts(date)
        .filter((part) => ['year', 'month', 'day'].includes(part.type))
        .map((part) => [part.type, Number.parseInt(part.value, 10)]),
);

export const persianDigits = (value) => String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);

export const gregorianToJalali = (isoDate) => {
    if (! /^\d{4}-\d{2}-\d{2}$/.test(isoDate || '')) return null;

    const date = new Date(`${isoDate}T12:00:00Z`);
    if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== isoDate) return null;

    return parts(date);
};

export const jalaliToGregorian = (year, month, day) => {
    const approximateGregorianYear = year + 621;
    const cursor = new Date(Date.UTC(approximateGregorianYear, 1, 15, 12));

    for (let offset = 0; offset < 410; offset += 1) {
        const candidate = new Date(cursor.getTime() + (offset * 86400000));
        const jalali = parts(candidate);

        if (jalali.year === year && jalali.month === month && jalali.day === day) {
            return candidate.toISOString().slice(0, 10);
        }

        if (jalali.year > year || (jalali.year === year && jalali.month > month)) break;
    }

    return null;
};

export const jalaliMonthLength = (year, month) => {
    for (let day = 31; day >= 29; day -= 1) {
        if (jalaliToGregorian(year, month, day)) return day;
    }

    return month <= 6 ? 31 : 30;
};

export const formatJalali = ({ year, month, day }) => persianDigits(
    `${year}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}`,
);

