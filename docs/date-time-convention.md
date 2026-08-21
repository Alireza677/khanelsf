# Date & Time Convention

1. Database dates are always Gregorian and ISO-compatible; never add Jalali string columns.
2. Eloquent date/date-time attributes use Laravel casts and Carbon.
3. Persian-facing output uses `App\Support\PersianDate` or `<x-persian-date>`.
4. Filament `DatePicker` and `DateTimePicker` fields use `->jalali()`.
5. Filament date columns and entries use `->jalaliDate()` / `->jalaliDateTime()`.
6. Jalali-to-Gregorian conversion happens at the UI boundary. Validation, queries, and business logic receive canonical Gregorian values.
7. Time conversion uses `config('app.timezone')`; Jalali formatting must not introduce a timezone.
8. APIs, CSV machine fields, sitemap `lastmod`, JSON-LD dates, and HTML `datetime` attributes remain Gregorian/ISO.
9. New user-facing dates and date filters must follow these rules.
