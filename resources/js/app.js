import './bootstrap';
import 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';

const themeStorageKey = 'tribun-theme';

const currentTheme = () => document.documentElement.dataset.bsTheme === 'light' ? 'light' : 'dark';

const updateThemeControls = () => {
    const isDark = currentTheme() === 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = isDark ? 'Açık temaya geç' : 'Koyu temaya geç';
        const icon = button.querySelector('[data-theme-icon]');

        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);

        if (icon) {
            icon.className = `bi ${isDark ? 'bi-sun' : 'bi-moon'}`;
        }
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
