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
