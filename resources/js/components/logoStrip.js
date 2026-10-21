/**
 * Horizontal scroller with arrow buttons that respect text direction.
 */
export function logoStrip() {
    return {
        canBack: false,
        canForward: true,

        init() {
            this.$nextTick(() => this.update());
            this.$refs.track.addEventListener('scroll', () => this.update(), { passive: true });
            window.addEventListener('resize', () => this.update());
        },

        update() {
            const el = this.$refs.track;
            const max = el.scrollWidth - el.clientWidth;
            const pos = Math.abs(el.scrollLeft);
            this.canBack = pos > 4;
            this.canForward = pos < max - 4;
        },

        scroll(direction) {
            const el = this.$refs.track;
            const rtl = getComputedStyle(el).direction === 'rtl';
            const amount = el.clientWidth * 0.8 * direction * (rtl ? -1 : 1);
            el.scrollBy({ left: amount, behavior: 'smooth' });
        },
    };
}
