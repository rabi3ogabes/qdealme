/**
 * Infinite scroll for card grids. Fetches the next page as an HTML fragment
 * (same route with ?partial=1) and appends it, with a manual button fallback
 * for people who prefer not to auto-load.
 */
export function loadMore({ nextUrl, auto = true }) {
    return {
        nextUrl,
        loading: false,
        error: false,
        observer: null,

        init() {
            if (!auto || !this.nextUrl || !('IntersectionObserver' in window)) return;

            this.observer = new IntersectionObserver(
                (entries) => {
                    if (entries.some((e) => e.isIntersecting)) this.load();
                },
                { rootMargin: '600px 0px' },
            );
            this.observer.observe(this.$refs.sentinel);
        },

        async load() {
            if (this.loading || !this.nextUrl) return;
            this.loading = true;
            this.error = false;

            try {
                const url = new URL(this.nextUrl, window.location.origin);
                url.searchParams.set('partial', '1');
                const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
                if (!res.ok) throw new Error(res.statusText);

                const html = await res.text();
                const tpl = document.createElement('template');
                tpl.innerHTML = html.trim();

                const next = tpl.content.querySelector('[data-next-url]');
                this.nextUrl = next ? next.dataset.nextUrl || null : null;
                next?.remove();

                this.$refs.grid.append(...tpl.content.childNodes);

                if (!this.nextUrl) this.observer?.disconnect();
            } catch {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },
    };
}
