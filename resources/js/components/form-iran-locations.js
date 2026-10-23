export const normalizePersianSearch = (value) => String(value).normalize('NFKC')
    .replace(/ي|ى/g, 'ی').replace(/ك/g, 'ک').replace(/[\u064B-\u065F\u0670\u0640]/g, '')
    .replace(/[\s\u200c\u200d]+/g, '').toLocaleLowerCase('fa');

export const initIranLocations = () => {
    document.querySelectorAll('form:has([data-iran-location])').forEach((form) => {
        if (! form.querySelector('[data-iran-location]:not([data-location-ready="true"])')) return;
        const data = form.querySelector('[data-iran-locations]');
        if (! data) return;
        // Read once for the newly inserted fields of this form instance.
        const cities = JSON.parse(data.textContent);
        form.querySelectorAll('[data-iran-location]').forEach((root) => {
            if (root.dataset.locationReady === 'true') return;
            const province = root.querySelector('[data-location-part="province_id"]');
            const city = root.querySelector('[data-location-part="city_id"]');
            if (! province || ! city) return;
            root.dataset.locationReady = 'true';
            const initialProvince = province.value;
            const initialCity = city.value;

            const syncValidity = () => {
                // Optional means both empty or both filled; partial answers must be completed.
                city.required = province.required || province.value !== '';
            };
            const refreshCities = () => {
                const options = [new Option(province.value ? 'انتخاب کنید' : 'ابتدا استان را انتخاب کنید', '')];
                cities.filter((item) => item.province_id === province.value).forEach((item) => {
                    options.push(new Option(item.name, item.id));
                });
                city.replaceChildren(...options);
                city.value = '';
                city.disabled = province.value === '';
                syncValidity();
                city.dispatchEvent(new CustomEvent('form-select:refresh'));
                city.dispatchEvent(new Event('change', { bubbles: true }));
            };
            province.addEventListener('change', refreshCities);
            form.addEventListener('reset', () => {
                // The native reset occurs after the reset event.
                queueMicrotask(() => {
                    refreshCities();
                    if (province.value === initialProvince) city.value = initialCity;
                    city.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
            syncValidity();
        });
    });
};
