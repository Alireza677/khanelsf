export default function destinationTargetPicker({ state = null, target = 'page', label = '', endpoint, mode = 'reference', disabled = false }) {
    let request = null;
    let labelRequest = null;
    let sequence = 0;
    let labelSequence = 0;

    return {
        state, target, label, endpoint, mode, disabled,
        query: '', category: '', categories: [], items: [], total: 0, page: 1, lastPage: 1,
        loading: false, error: '', modalOpen: false,

        init() {
            this.$watch('state', () => this.refreshLabel());
        },

        async refreshLabel() {
            const current = ++labelSequence;
            labelRequest?.abort();
            if (!this.state) {
                this.label = 'موردی انتخاب نشده است';
                return;
            }
            labelRequest = new AbortController();
            try {
                const url = new URL(this.endpoint, window.location.href || window.location.origin);
                url.search = new URLSearchParams({ target: this.target, selected: this.state });
                const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: labelRequest.signal });
                if (!response.ok) throw new Error('label');
                const data = await response.json();
                if (current === labelSequence) this.label = data.label;
            } catch (error) {
                if (current === labelSequence && error.name !== 'AbortError') this.label = 'دریافت عنوان مقصد ناموفق بود';
            }
        },

        open() {
            if (this.disabled) return;
            this.query = '';
            this.category = '';
            this.modalOpen = true;
            this.$refs.dialog.showModal();
            this.$nextTick(() => this.$refs.search.focus());
            return this.load(1);
        },

        close() {
            this.modalOpen = false;
            sequence++;
            request?.abort();
            this.loading = false;
            this.$refs.dialog.close();
            this.$nextTick(() => {
                this.$refs.trigger.focus();
                this.$dispatch('destination-picker-closed');
            });
        },

        invalidate() {
            sequence++;
            request?.abort();
            this.items = [];
            this.loading = true;
        },

        async load(page = 1) {
            request?.abort();
            request = new AbortController();
            const current = ++sequence;
            this.items = [];
            this.loading = true;
            this.error = '';
            try {
                const url = new URL(this.endpoint, window.location.href || window.location.origin);
                url.search = new URLSearchParams({ target: this.target, q: this.query, page });
                if (this.target === 'project' && this.category) url.searchParams.set('category_id', this.category);
                const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
                if (!response.ok) throw new Error('browse');
                const data = await response.json();
                if (current !== sequence) return;
                this.items = data.items;
                this.total = data.total;
                this.page = data.page;
                this.lastPage = data.last_page;
                this.categories = data.categories || [];
            } catch (error) {
                if (current === sequence && error.name !== 'AbortError') this.error = 'دریافت فهرست ناموفق بود. دوباره تلاش کنید.';
            } finally {
                if (current === sequence) this.loading = false;
            }
        },

        choose(item) {
            if (this.disabled || !item.selectable || this.loading) return;
            if (this.mode === 'url') {
                this.$dispatch('destination-picked', item);
            } else {
                this.state = item.reference_id;
                this.label = item.title;
            }
            this.close();
        },

        clear() {
            if (this.disabled) return;
            this.state = null;
            this.label = 'موردی انتخاب نشده است';
        },

        destroy() {
            sequence++;
            labelSequence++;
            request?.abort();
            labelRequest?.abort();
            if (this.$refs.dialog.open) this.$refs.dialog.close();
        },
    };
}
