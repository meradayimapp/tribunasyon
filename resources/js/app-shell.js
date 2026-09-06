// Alpine owns the window listeners declared on .app-shell and removes them
// when Livewire replaces the layout. No persistent scroll subscription.
export default () => ({
    mobileMenuOpen: false,
    headerHidden: false,
    lastScrollY: 0,
    scrollDelta: 0,

    init() {
        this.resetHeader();
    },

    resetHeader() {
        this.headerHidden = false;
        this.lastScrollY = Math.max(0, window.scrollY);
        this.scrollDelta = 0;
        if (window.matchMedia('(min-width: 768px)').matches) {
            this.mobileMenuOpen = false;
        }
    },

    onScroll() {
        const maximum = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        const y = Math.min(maximum, Math.max(0, window.scrollY));
        const header = this.$refs.header;
        const focused = document.activeElement;
        const editingHeader = header?.contains(focused)
            && (focused.matches('input, textarea, select, [contenteditable="true"]') || focused.matches(':focus-visible'));
        const locked = this.mobileMenuOpen
            || editingHeader
            || [...document.querySelectorAll('.dropdown-menu.show, .search-suggestions, [data-header-dropdown][aria-expanded="true"]')]
                .some((element) => element.getClientRects().length > 0);

        if (window.matchMedia('(min-width: 768px)').matches || y < 20 || locked) {
            this.headerHidden = false;
            this.scrollDelta = 0;
        } else {
            const delta = y - this.lastScrollY;
            if (delta !== 0) {
                this.scrollDelta = Math.sign(delta) === Math.sign(this.scrollDelta)
                    ? this.scrollDelta + delta : delta;
                if (Math.abs(this.scrollDelta) >= 8) {
                    this.headerHidden = this.scrollDelta > 0;
                    this.scrollDelta = 0;
                }
            }
        }

        this.lastScrollY = y;
    },

    destroy() {
        document.body.classList.remove('mobile-menu-open');
    },
});
