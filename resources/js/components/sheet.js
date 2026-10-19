/**
 * Generic bottom sheet / dialog state with scroll lock and Escape to close.
 */
export function sheet({ open = false } = {}) {
    return {
        open,

        init() {
            this.$watch('open', (v) => document.documentElement.classList.toggle('overflow-hidden', v));
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.open) this.open = false;
            });
        },

        show() {
            this.open = true;
        },

        hide() {
            this.open = false;
        },

        toggle() {
            this.open = !this.open;
        },
    };
}
