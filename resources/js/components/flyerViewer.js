/**
 * Full-bleed flyer viewer.
 *
 *  - Pages: arrows, keyboard (← → Home End Esc), swipe, thumbnails, deep link
 *    via ?page=N (history.replaceState keeps the URL shareable).
 *  - RTL aware: in Arabic the "next" page is to the left, so arrow keys and
 *    swipe directions are mirrored.
 *  - Zoom: wheel, pinch, double-tap; pan while zoomed; bounds clamped.
 *  - Preloads the neighbouring pages so turning feels instant.
 *  - Hotspots: verified offers are drawn as tappable regions on the page.
 */
export function flyerViewer({ pages, start = 1, rtl = false, offersByPage = {}, highlightOffer = 0, shareTitle = '', shareUrl = '' }) {
    return {
        pages,
        rtl,
        offersByPage,
        current: Math.min(Math.max(start, 1), pages.length),
        scale: 1,
        tx: 0,
        ty: 0,
        panning: false,
        swipeStart: null,
        pinch: null,
        lastTap: 0,
        thumbsOpen: false,
        detailsOpen: false,
        offersOpen: false,
        shareOpen: false,
        reportOpen: false,
        copied: false,
        selectedOffer: null,
        chromeVisible: true,
        hideTimer: null,
        loaded: {},

        init() {
            this.preload(this.current);
            this.syncUrl(false);

            if (highlightOffer) {
                const match = (this.offersByPage[this.current] || []).find((o) => o.id === highlightOffer);
                if (match) this.selectedOffer = match;
            }

            window.addEventListener('keydown', (e) => this.onKey(e));
            window.addEventListener('popstate', () => {
                const n = parseInt(new URLSearchParams(location.search).get('page') || '1', 10);
                if (n !== this.current) this.go(n, false);
            });

            this.$watch('current', (n) => {
                this.resetZoom();
                this.preload(n);
                this.selectedOffer = null;
                this.$nextTick(() => this.$refs.thumbs?.querySelector(`[data-thumb="${n}"]`)?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' }));
            });

            this.scheduleHide();
        },

        /* ---------------- navigation ---------------- */

        get page() {
            return this.pages[this.current - 1];
        },

        get total() {
            return this.pages.length;
        },

        get hasPrev() {
            return this.current > 1;
        },

        get hasNext() {
            return this.current < this.total;
        },

        get currentOffers() {
            return this.offersByPage[this.current] || [];
        },

        go(n, push = true) {
            const target = Math.min(Math.max(n, 1), this.total);
            if (target === this.current) return;
            this.current = target;
            this.syncUrl(push);
            this.thumbsOpen = false;
            this.wake();
        },

        next() {
            this.go(this.current + 1);
        },

        prev() {
            this.go(this.current - 1);
        },

        /** Visual "forward" button: on the end side of reading direction. */
        forward() {
            this.next();
        },

        backward() {
            this.prev();
        },

        syncUrl(push) {
            const url = new URL(location.href);
            if (this.current > 1) url.searchParams.set('page', String(this.current));
            else url.searchParams.delete('page');
            url.searchParams.delete('offer');
            const method = push ? 'pushState' : 'replaceState';
            history[method]({ page: this.current }, '', url);
        },

        preload(n) {
            [n, n + 1, n - 1].forEach((i) => {
                const p = this.pages[i - 1];
                if (!p || this.loaded[i]) return;
                const img = new Image();
                if (p.srcset) img.srcset = p.srcset;
                img.src = p.url;
                this.loaded[i] = true;
            });
        },

        onKey(e) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;

            switch (e.key) {
                case 'ArrowRight':
                    e.preventDefault();
                    this.rtl ? this.prev() : this.next();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.rtl ? this.next() : this.prev();
                    break;
                case 'Home':
                    this.go(1);
                    break;
                case 'End':
                    this.go(this.total);
                    break;
                case 'Escape':
                    if (this.anyOpen()) this.closeAll();
                    else if (this.scale > 1) this.resetZoom();
                    break;
                case '+':
                case '=':
                    this.zoomTo(this.scale * 1.25);
                    break;
                case '-':
                    this.zoomTo(this.scale / 1.25);
                    break;
                default:
                    return;
            }
            this.wake();
        },

        anyOpen() {
            return this.thumbsOpen || this.detailsOpen || this.offersOpen || this.shareOpen || this.reportOpen || !!this.selectedOffer;
        },

        closeAll() {
            this.thumbsOpen = this.detailsOpen = this.offersOpen = this.shareOpen = this.reportOpen = false;
            this.selectedOffer = null;
        },

        /* ---------------- chrome auto-hide ---------------- */

        wake() {
            this.chromeVisible = true;
            this.scheduleHide();
        },

        scheduleHide() {
            clearTimeout(this.hideTimer);
            this.hideTimer = setTimeout(() => {
                if (!this.anyOpen() && this.scale === 1) this.chromeVisible = false;
            }, 3500);
        },

        /* ---------------- zoom & pan ---------------- */

        get transform() {
            return `translate(${this.tx}px, ${this.ty}px) scale(${this.scale})`;
        },

        resetZoom() {
            this.scale = 1;
            this.tx = 0;
            this.ty = 0;
        },

        zoomTo(scale, cx = null, cy = null) {
            const stage = this.$refs.stage.getBoundingClientRect();
            const next = Math.min(Math.max(scale, 1), 5);

            if (cx !== null && cy !== null) {
                // Keep the point under the cursor fixed while scaling.
                const px = cx - stage.left - stage.width / 2;
                const py = cy - stage.top - stage.height / 2;
                const ratio = next / this.scale;
                this.tx = px - (px - this.tx) * ratio;
                this.ty = py - (py - this.ty) * ratio;
            }

            this.scale = next;
            if (next === 1) {
                this.tx = 0;
                this.ty = 0;
            }
            this.clamp();
            this.wake();
        },

        clamp() {
            const stage = this.$refs.stage.getBoundingClientRect();
            const img = this.$refs.image?.getBoundingClientRect();
            if (!img) return;
            const halfW = Math.max(0, (img.width - stage.width) / 2);
            const halfH = Math.max(0, (img.height - stage.height) / 2);
            this.tx = Math.min(halfW, Math.max(-halfW, this.tx));
            this.ty = Math.min(halfH, Math.max(-halfH, this.ty));
        },

        onWheel(e) {
            if (!e.ctrlKey && !e.metaKey && this.scale === 1) return;
            e.preventDefault();
            const factor = e.deltaY < 0 ? 1.12 : 1 / 1.12;
            this.zoomTo(this.scale * factor, e.clientX, e.clientY);
        },

        onPointerDown(e) {
            this.wake();
            if (e.pointerType === 'mouse' && e.button !== 0) return;

            this.$refs.stage.setPointerCapture?.(e.pointerId);

            if (this.scale > 1) {
                this.panning = { x: e.clientX, y: e.clientY, tx: this.tx, ty: this.ty, moved: false };
            } else {
                this.swipeStart = { x: e.clientX, y: e.clientY, t: Date.now() };
            }
        },

        onPointerMove(e) {
            if (this.panning) {
                const dx = e.clientX - this.panning.x;
                const dy = e.clientY - this.panning.y;
                if (Math.abs(dx) + Math.abs(dy) > 3) this.panning.moved = true;
                this.tx = this.panning.tx + dx;
                this.ty = this.panning.ty + dy;
                this.clamp();
            }
        },

        onPointerUp(e) {
            if (this.panning) {
                const moved = this.panning.moved;
                this.panning = false;
                if (!moved) this.handleTap(e);
                return;
            }

            if (this.swipeStart) {
                const dx = e.clientX - this.swipeStart.x;
                const dy = e.clientY - this.swipeStart.y;
                const dt = Date.now() - this.swipeStart.t;
                this.swipeStart = null;

                if (Math.abs(dx) > 48 && Math.abs(dx) > Math.abs(dy) * 1.5 && dt < 700) {
                    // Swiping toward the start of reading direction = next page.
                    const toStart = this.rtl ? dx > 0 : dx < 0;
                    toStart ? this.next() : this.prev();
                    return;
                }

                if (Math.abs(dx) < 8 && Math.abs(dy) < 8) this.handleTap(e);
            }
        },

        handleTap(e) {
            const now = Date.now();
            if (now - this.lastTap < 300) {
                this.lastTap = 0;
                this.scale > 1 ? this.resetZoom() : this.zoomTo(2.5, e.clientX, e.clientY);
                return;
            }
            this.lastTap = now;
            this.chromeVisible = !this.chromeVisible;
            if (this.chromeVisible) this.scheduleHide();
        },

        onTouchStart(e) {
            if (e.touches.length === 2) {
                this.swipeStart = null;
                this.panning = false;
                this.pinch = {
                    dist: this.touchDist(e),
                    scale: this.scale,
                    cx: (e.touches[0].clientX + e.touches[1].clientX) / 2,
                    cy: (e.touches[0].clientY + e.touches[1].clientY) / 2,
                };
            }
        },

        onTouchMove(e) {
            if (this.pinch && e.touches.length === 2) {
                e.preventDefault();
                const ratio = this.touchDist(e) / this.pinch.dist;
                this.zoomTo(this.pinch.scale * ratio, this.pinch.cx, this.pinch.cy);
            }
        },

        onTouchEnd(e) {
            if (e.touches.length < 2) this.pinch = null;
        },

        touchDist(e) {
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            return Math.hypot(dx, dy);
        },

        /* ---------------- hotspots & offers ---------------- */

        hotspotStyle(offer) {
            const b = offer.bbox || { x: 0, y: 0, w: 0, h: 0 };
            return `left:${b.x * 100}%;top:${b.y * 100}%;width:${b.w * 100}%;height:${b.h * 100}%`;
        },

        selectOffer(offer) {
            this.selectedOffer = offer;
            this.offersOpen = false;
            this.wake();
        },

        /* ---------------- sharing ---------------- */

        get pageUrl() {
            return location.href;
        },

        async share() {
            if (navigator.share) {
                try {
                    await navigator.share({ title: shareTitle, url: this.pageUrl });
                    return;
                } catch {
                    /* user dismissed; fall through to the menu */
                }
            }
            this.shareOpen = true;
        },

        shareLink(network) {
            const u = encodeURIComponent(this.pageUrl);
            const t = encodeURIComponent(shareTitle);
            return {
                whatsapp: `https://wa.me/?text=${t}%20${u}`,
                x: `https://twitter.com/intent/tweet?text=${t}&url=${u}`,
                facebook: `https://www.facebook.com/sharer/sharer.php?u=${u}`,
            }[network];
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(this.pageUrl);
                this.copied = true;
                setTimeout(() => (this.copied = false), 1800);
            } catch {
                prompt(shareUrl, this.pageUrl);
            }
        },
    };
}
