/**
 * Minimal toast stack. Server flashes are pushed on load; client code can
 * dispatch `qd:toast` with { message, tone }.
 */
export function toast({ initial = null } = {}) {
    return {
        items: [],

        init() {
            if (initial) this.push(initial, 'success');
            window.addEventListener('qd:toast', (e) => this.push(e.detail.message, e.detail.tone || 'success'));
        },

        push(message, tone = 'success') {
            if (!message) return;
            const id = Date.now() + Math.random();
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), 4200);
        },

        dismiss(id) {
            this.items = this.items.filter((i) => i.id !== id);
        },
    };
}
