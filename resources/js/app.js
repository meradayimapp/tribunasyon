import './bootstrap';
import 'bootstrap';
import appShell from './app-shell';

window.appShell = appShell;

const themeStorageKey = 'tribun-theme';

const currentTheme = () => document.documentElement.dataset.bsTheme === 'light' ? 'light' : 'dark';

const updateThemeControls = () => {
    const isDark = currentTheme() === 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = isDark ? 'Açık temaya geç' : 'Koyu temaya geç';

        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    });
};

const setTheme = (theme, persist = true) => {
    const normalized = theme === 'light' ? 'light' : 'dark';
    document.documentElement.dataset.bsTheme = normalized;

    if (persist) {
        try {
            localStorage.setItem(themeStorageKey, normalized);
        } catch (error) {
            // The selected theme still applies for this page when storage is unavailable.
        }
    }

    updateThemeControls();
};

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const toggle = event.target.closest('[data-theme-toggle]');

    if (toggle) {
        setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }
});

document.addEventListener('DOMContentLoaded', updateThemeControls);
document.addEventListener('livewire:navigated', updateThemeControls);

window.imageUploadPreview = (initialUrl = null) => ({
    preview: initialUrl,
    objectUrl: null,
    removed: false,

    choose(event) {
        const [file] = event.target.files ?? [];

        if (!file) {
            return;
        }

        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
        }

        this.objectUrl = URL.createObjectURL(file);
        this.preview = this.objectUrl;
        this.removed = false;
    },

    clear() {
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }

        this.$refs.file.value = '';
        this.preview = null;
        this.removed = true;
    },
});

window.postMediaManager = (existingMedia = [], maximum = 10) => ({
    items: existingMedia.map((media) => ({ key: `existing-${media.id}`, kind: 'existing', id: media.id, url: media.url })),
    maximum,
    message: '',
    dragging: null,

    addFiles(event) {
        const files = Array.from(event.target.files ?? []);
        const available = Math.max(0, this.maximum - this.items.length);

        if (files.length > available) {
            this.message = `Bir gönderide en fazla ${this.maximum} görsel olabilir.`;
        } else {
            this.message = '';
        }

        files.slice(0, available).forEach((file) => {
            this.items.push({
                key: `new-${window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}-${Math.random()}`}`,
                kind: 'new',
                file,
                url: URL.createObjectURL(file),
            });
        });

        this.syncInput();
    },

    remove(index) {
        const [removed] = this.items.splice(index, 1);

        if (removed?.kind === 'new') {
            URL.revokeObjectURL(removed.url);
        }

        this.message = '';
        this.syncInput();
    },

    move(from, to) {
        if (to < 0 || to >= this.items.length || from === to) {
            return;
        }

        const [item] = this.items.splice(from, 1);
        this.items.splice(to, 0, item);
        this.syncInput();
    },

    startDrag(index) {
        this.dragging = index;
    },

    dropAt(index) {
        if (this.dragging !== null) {
            this.move(this.dragging, index);
        }

        this.dragging = null;
    },

    token(item) {
        if (item.kind === 'existing') {
            return `existing:${item.id}`;
        }

        return `new:${this.items.filter((candidate) => candidate.kind === 'new').indexOf(item)}`;
    },

    syncInput() {
        if (!this.$refs.mediaInput || typeof DataTransfer === 'undefined') {
            return;
        }

        const transfer = new DataTransfer();
        this.items.filter((item) => item.kind === 'new').forEach((item) => transfer.items.add(item.file));
        this.$refs.mediaInput.files = transfer.files;
    },
});

window.postMediaCarousel = (total) => ({
    active: 0,
    total,

    sync() {
        const width = this.$refs.track?.clientWidth ?? 0;

        if (width > 0) {
            this.active = Math.min(this.total - 1, Math.max(0, Math.round(this.$refs.track.scrollLeft / width)));
        }
    },

    goTo(index) {
        if (index < 0 || index >= this.total || !this.$refs.track) {
            return;
        }

        this.$refs.track.scrollTo({ left: this.$refs.track.clientWidth * index, behavior: 'smooth' });
    },
});

window.playerChatScroll = () => ({
    stickToTop: true,

    init() {
        this.$nextTick(() => this.scrollToTop());
    },

    onScroll() {
        const log = this.$refs.log;
        this.stickToTop = !log || log.scrollTop < 72;
    },

    afterUpdate() {
        if (this.stickToTop) this.$nextTick(() => this.scrollToTop());
    },

    afterLatest() {
        this.stickToTop = true;
        this.$nextTick(() => this.scrollToTop());
    },

    scrollToTop() {
        const log = this.$refs.log;
        if (log) log.scrollTo({ top: 0, behavior: 'smooth' });
    },
});

window.matchLiveState = (url, initial) => ({
    url,
    timer: null,
    isLive: Boolean(initial.is_live),
    isFinished: Boolean(initial.is_finished),
    status: initial.status,
    homeScore: initial.score?.home ?? null,
    awayScore: initial.score?.away ?? null,
    minute: initial.minute ?? null,
    statusDisplay: initial.status_display,
    events: Array.isArray(initial.events) ? initial.events : [],

    get scoreKnown() {
        return this.homeScore !== null || this.awayScore !== null;
    },

    get centerStatus() {
        return this.status === 'finished' ? 'MS' : this.statusDisplay;
    },

    init() {
        if (this.isLive && !document.hidden) this.start();
    },

    destroy() {
        this.stop();
    },

    visibilityChanged() {
        if (document.hidden) {
            this.stop();
        } else if (this.isLive && !this.isFinished) {
            this.refresh();
            this.start();
        }
    },

    start() {
        if (this.timer || !this.isLive || this.isFinished || document.hidden) return;
        this.timer = window.setInterval(() => this.refresh(), 25000);
    },

    stop() {
        if (this.timer) window.clearInterval(this.timer);
        this.timer = null;
    },

    async refresh() {
        if (document.hidden || !this.isLive || this.isFinished) return;

        try {
            const response = await fetch(this.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const state = await response.json();
            this.isLive = Boolean(state.is_live);
            this.isFinished = Boolean(state.is_finished);
            this.status = state.status;
            this.homeScore = state.score?.home ?? null;
            this.awayScore = state.score?.away ?? null;
            this.minute = state.minute ?? null;
            this.statusDisplay = state.status_display;
            this.events = Array.isArray(state.events) ? state.events : [];
            this.$nextTick(() => this.renderEvents());
            if (!this.isLive || this.isFinished) this.stop();
        } catch (error) {
            // Keep the last database-backed state visible during transient failures.
        }
    },

    renderEvents() {
        const list = this.$refs.eventsList;

        if (!list) return;

        const rows = this.events.map((event) => {
            const side = ['home', 'away'].includes(event.side) ? event.side : 'neutral';
            const row = document.createElement('article');
            row.className = `match-event side-${side}`;
            row.dataset.eventType = event.type || 'event';

            const time = document.createElement('time');
            time.textContent = event.time === null || event.time === undefined || event.time === '' ? '–' : `${event.time}′`;

            const copy = document.createElement('div');
            copy.className = 'match-event-copy';
            const symbol = document.createElement('span');
            symbol.className = 'match-event-symbol';
            symbol.setAttribute('aria-hidden', 'true');
            const details = document.createElement('span');
            const label = document.createElement('strong');
            label.textContent = event.label || event.type || 'Olay';
            details.append(label);

            [
                event.player_name,
                event.player_in ? `Giren: ${event.player_in}` : null,
                event.player_out ? `Çıkan: ${event.player_out}` : null,
            ].filter(Boolean).forEach((value) => {
                const detail = document.createElement('small');
                detail.textContent = value;
                details.append(detail);
            });

            copy.append(symbol, details);

            if (event.score) {
                const score = document.createElement('b');
                score.textContent = event.score;
                copy.append(score);
            }

            row.append(time, copy);

            return row;
        });

        list.replaceChildren(...rows);
    },
});
