/**
 * Theme switcher: "auto" follows the OS, "light"/"dark" are explicit. The
 * inline script in the layout applies the stored choice before first paint,
 * so this component only needs to keep the state and react to changes.
 */
export function theme() {
    return {
        setting: 'auto',
        media: null,

        init() {
            this.setting = this.read();
            this.media = window.matchMedia('(prefers-color-scheme: dark)');
            this.media.addEventListener('change', () => this.apply());
            this.apply();
        },

        read() {
            try {
                const stored = localStorage.getItem('theme');
                return ['auto', 'light', 'dark'].includes(stored) ? stored : 'auto';
            } catch {
                return 'auto';
            }
        },

        set(value) {
            this.setting = value;
            try {
                localStorage.setItem('theme', value);
            } catch {
                /* private mode */
            }
            this.apply();
        },

        cycle() {
            const order = ['auto', 'light', 'dark'];
            this.set(order[(order.indexOf(this.setting) + 1) % order.length]);
        },

        apply() {
            const dark = this.setting === 'dark' || (this.setting === 'auto' && this.media.matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.documentElement.dataset.themeSetting = this.setting;
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#141114' : '#fbf9f5');
        },

        get isDark() {
            return document.documentElement.dataset.theme === 'dark';
        },
    };
}
