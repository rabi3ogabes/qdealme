/**
 * Command-palette style search (⌘K / Ctrl+K). Fetches grouped suggestions
 * from the suggest endpoint with a small debounce and supports keyboard
 * navigation through the flattened result list.
 */
export function searchPalette({ suggestUrl, searchUrl, minLength = 2 }) {
    return {
        open: false,
        query: '',
        loading: false,
        results: { retailers: [], flyers: [], offers: [], categories: [] },
        more: null,
        active: -1,
        timer: null,
        controller: null,

        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.toggle();
                }
                if (e.key === '/' && !this.open && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                    e.preventDefault();
                    this.show();
                }
            });
            window.addEventListener('qd:open-search', () => this.show());
        },

        toggle() {
            this.open ? this.hide() : this.show();
        },

        show() {
            this.open = true;
            document.documentElement.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.input?.focus());
        },

        hide() {
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
        },

        onInput() {
            clearTimeout(this.timer);
            this.active = -1;

            if (this.query.trim().length < minLength) {
                this.results = { retailers: [], flyers: [], offers: [], categories: [] };
                this.more = null;
                return;
            }

            this.timer = setTimeout(() => this.fetch(), 180);
        },

        async fetch() {
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;

            try {
                const url = new URL(suggestUrl, window.location.origin);
                url.searchParams.set('q', this.query.trim());
                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: this.controller.signal,
                });
                if (!res.ok) throw new Error(res.statusText);
                const data = await res.json();
                this.results = data;
                this.more = data.more ?? null;
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.results = { retailers: [], flyers: [], offers: [], categories: [] };
                }
            } finally {
                this.loading = false;
            }
        },

        get flat() {
            return [
                ...this.results.retailers,
                ...this.results.flyers,
                ...this.results.offers,
                ...this.results.categories,
            ];
        },

        get hasResults() {
            return this.flat.length > 0;
        },

        get isEmpty() {
            return !this.loading && this.query.trim().length >= minLength && !this.hasResults;
        },

        indexOf(group, i) {
            const order = ['retailers', 'flyers', 'offers', 'categories'];
            let offset = 0;
            for (const g of order) {
                if (g === group) break;
                offset += this.results[g].length;
            }
            return offset + i;
        },

        move(delta) {
            if (!this.hasResults) return;
            const n = this.flat.length;
            this.active = (this.active + delta + n) % n;
            this.$nextTick(() => {
                this.$root.querySelector(`[data-index="${this.active}"]`)?.scrollIntoView({ block: 'nearest' });
            });
        },

        submit() {
            if (this.active >= 0 && this.flat[this.active]) {
                window.location.href = this.flat[this.active].url;
                return;
            }
            const q = this.query.trim();
            if (q.length) {
                const url = new URL(searchUrl, window.location.origin);
                url.searchParams.set('q', q);
                window.location.href = url.toString();
            }
        },
    };
}
