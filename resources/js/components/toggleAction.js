/**
 * Follow / save buttons. Posts to the toggle endpoint and flips state from
 * the JSON response. Guests are sent to the login page by the server.
 */
export function toggleAction({ url, active = false, authenticated = false, loginUrl = '' }) {
    return {
        active,
        busy: false,

        async toggle() {
            if (!authenticated) {
                window.location.href = loginUrl;
                return;
            }
            if (this.busy) return;
            this.busy = true;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });
                if (res.status === 401 || res.status === 419) {
                    window.location.href = loginUrl;
                    return;
                }
                if (!res.ok) throw new Error(res.statusText);
                const data = await res.json();
                this.active = data.following ?? data.saved ?? !this.active;
                window.dispatchEvent(new CustomEvent('qd:toast', { detail: { message: data.message } }));
            } catch {
                window.dispatchEvent(new CustomEvent('qd:toast', { detail: { message: this.$root.dataset.errorMessage, tone: 'danger' } }));
            } finally {
                this.busy = false;
            }
        },
    };
}
