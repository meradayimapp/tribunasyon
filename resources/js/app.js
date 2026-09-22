import './bootstrap';
import 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
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

window.postSourcesEditor = (existingSources = [], maximum = 10) => ({
    rows: (existingSources.length ? existingSources : [{ label: '', url: '' }]).map((source, index) => ({
        key: `source-${index}`,
        label: source.label ?? '',
        url: source.url ?? '',
    })),
    maximum,
    nextKey: existingSources.length + 1,

    add() {
        if (this.rows.length < this.maximum) {
            this.rows.push({ key: `source-${this.nextKey++}`, label: '', url: '' });
        }
    },

    remove(index) {
        this.rows.splice(index, 1);
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

window.matchLiveState = (url, initial, defaultTab = 'ozet') => ({
    url,
    timer: null,
    wakeTimer: null,
    activeTab: defaultTab,
    lineupSide: 'home',
    tabs: ['sohbet', 'ozet', 'istatistik', 'kadro', 'puan-durumu', 'h2h'],
    isLive: Boolean(initial.is_live),
    isHalfTime: Boolean(initial.is_half_time),
    isFinished: Boolean(initial.is_finished),
    status: initial.status,
    homeScore: initial.score?.home ?? null,
    awayScore: initial.score?.away ?? null,
    minute: initial.minute ?? null,
    statusDisplay: initial.status_display,
    events: Array.isArray(initial.events) ? initial.events : [],
    stats: Array.isArray(initial.stats) ? initial.stats : [],
    lineups: initial.lineups && typeof initial.lineups === 'object' ? initial.lineups : {},
    lineupIsProjected: Boolean(initial.lineup_is_projected),
    lineupUpdatedAt: initial.lineup_updated_at ?? null,
    pollingActive: Boolean(initial.polling_active),
    pollingStartsAt: initial.polling_starts_at ?? null,

    get scoreKnown() {
        return this.homeScore !== null || this.awayScore !== null;
    },

    get hasLineups() {
        return ['home', 'away'].some((side) => Array.isArray(this.lineups?.[side]?.starting) && this.lineups[side].starting.length > 0);
    },

    get centerStatus() {
        if (this.status === 'finished') {
            return 'Bitti';
        }

        if (this.isLive) {
            return this.isHalfTime ? 'Devre Arası' : 'Canlı';
        }

        if (['scheduled', 'not_started'].includes(this.status)) {
            return 'Başlamadı';
        }

        return this.statusDisplay;
    },

    selectTab(tab, updateHistory = true) {
        if (!this.tabs.includes(tab)) return;
        this.activeTab = tab;
        if (updateHistory && window.location.hash !== `#${tab}`) window.history.pushState(null, '', `#${tab}`);
        this.$nextTick(() => document.getElementById(`match-tab-${tab}`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
    },

    hashChanged() {
        try {
            const hash = decodeURIComponent(window.location.hash.slice(1));
            if (this.tabs.includes(hash)) this.selectTab(hash, false);
        } catch (error) {
            // Ignore malformed fragments and keep the match-state default tab.
        }
    },

    tabKeydown(event) {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const index = this.tabs.indexOf(this.activeTab);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? this.tabs.length - 1
            : (index + (event.key === 'ArrowRight' ? 1 : -1) + this.tabs.length) % this.tabs.length;
        this.selectTab(this.tabs[next]);
        this.$nextTick(() => document.getElementById(`match-tab-${this.tabs[next]}`)?.focus());
    },

    init() {
        this.hashChanged();
        if (this.pollingActive && !document.hidden) {
            this.start();
        } else {
            this.armWakeup();
        }
    },

    destroy() {
        this.stop();
        if (this.wakeTimer) window.clearTimeout(this.wakeTimer);
    },

    visibilityChanged() {
        if (document.hidden) {
            this.stop();
        } else if (this.pollingActive && !this.isFinished) {
            this.refresh();
            this.start();
        } else {
            this.armWakeup();
        }
    },

    start() {
        if (this.timer || !this.pollingActive || this.isFinished || document.hidden) return;
        this.timer = window.setInterval(() => this.refresh(), 25000);
    },

    stop() {
        if (this.timer) window.clearInterval(this.timer);
        this.timer = null;
    },

    armWakeup() {
        if (this.wakeTimer || this.isFinished || !this.pollingStartsAt) return;
        const delay = new Date(this.pollingStartsAt).getTime() - Date.now();
        if (!Number.isFinite(delay)) return;
        if (delay <= 0) {
            this.pollingActive = true;
            if (!document.hidden) {
                this.refresh();
                this.start();
            }
            return;
        }
        this.wakeTimer = window.setTimeout(() => {
            this.wakeTimer = null;
            if (new Date(this.pollingStartsAt).getTime() > Date.now()) {
                this.armWakeup();
                return;
            }
            this.pollingActive = true;
            if (!document.hidden) {
                this.refresh();
                this.start();
            }
        }, Math.min(delay, 2147483647));
    },

    async refresh() {
        if (document.hidden || !this.pollingActive || this.isFinished) return;

        try {
            const response = await fetch(this.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const state = await response.json();
            this.isLive = Boolean(state.is_live);
            this.isHalfTime = Boolean(state.is_half_time);
            this.isFinished = Boolean(state.is_finished);
            this.status = state.status;
            this.homeScore = state.score?.home ?? null;
            this.awayScore = state.score?.away ?? null;
            this.minute = state.minute ?? null;
            this.statusDisplay = state.status_display;
            this.events = Array.isArray(state.events) ? state.events : [];
            this.stats = Array.isArray(state.stats) ? state.stats : [];
            const lineupChanged = state.lineup_updated_at !== this.lineupUpdatedAt;
            this.lineups = state.lineups && typeof state.lineups === 'object' ? state.lineups : {};
            this.lineupIsProjected = Boolean(state.lineup_is_projected);
            this.lineupUpdatedAt = state.lineup_updated_at ?? null;
            this.pollingActive = Boolean(state.polling_active);
            this.pollingStartsAt = state.polling_starts_at ?? this.pollingStartsAt;
            this.$nextTick(() => {
                this.renderEvents();
                this.renderStats();
                if (lineupChanged) this.renderLineups();
            });
            if (!this.pollingActive || this.isFinished) {
                this.stop();
                this.armWakeup();
            }
        } catch (error) {
            // Keep the last database-backed state visible during transient failures.
        }
    },

    renderEvents() {
        const list = this.$refs.eventsList;

        if (!list) return;

        const rows = this.events.map((event) => {
            const side = ['home', 'away'].includes(event.side) ? event.side : 'neutral';
            const type = ['goal', 'penalty_goal', 'yellow_card', 'red_card', 'second_yellow', 'substitution'].includes(event.type) ? event.type : 'event';
            const row = document.createElement('article');
            row.className = `match-event side-${side}`;
            row.dataset.eventType = type;

            const node = document.createElement('div');
            node.className = 'match-event-node';
            const time = document.createElement('time');
            time.textContent = event.time === null || event.time === undefined || event.time === '' ? '–' : `${event.time}′`;
            const symbol = document.createElement('span');
            symbol.className = 'match-event-symbol';
            symbol.setAttribute('aria-hidden', 'true');
            node.append(time, symbol);

            const content = document.createElement('div');
            content.className = 'match-event-content';
            const copy = document.createElement('span');
            copy.className = 'match-event-copy';
            const label = document.createElement('strong');
            label.textContent = event.label || event.type || 'Olay';
            copy.append(label);

            [
                event.player_name,
                event.player_in ? `Giren · ${event.player_in}` : null,
                event.player_out ? `Çıkan · ${event.player_out}` : null,
            ].filter(Boolean).forEach((value) => {
                const detail = document.createElement('small');
                detail.textContent = value;
                if (event.player_in && value.startsWith('Giren')) detail.className = 'player-in';
                if (event.player_out && value.startsWith('Çıkan')) detail.className = 'player-out';
                copy.append(detail);
            });

            content.append(copy);

            if (event.score) {
                const score = document.createElement('b');
                score.textContent = event.score;
                content.append(score);
            }

            row.append(node, content);

            return row;
        });

        list.replaceChildren(...rows);
    },

    renderStats() {
        const list = this.$refs.statsList;
        if (!list) return;
        const rows = this.stats.filter((stat) => stat && typeof stat === 'object').map((stat) => {
            const row = document.createElement('div');
            row.className = 'match-statistic';
            let share = Number(stat.home_share);
            if (!Number.isFinite(share)) {
                const number = (value) => Number(String(value ?? '').match(/\d+(?:[.,]\d+)?/)?.[0]?.replace(',', '.') ?? 0);
                const home = number(stat.home), away = number(stat.away);
                share = home + away > 0 ? 100 * home / (home + away) : 50;
            }
            share = Math.max(0, Math.min(100, share));
            row.style.setProperty('--home-share', `${share}%`);
            row.style.setProperty('--away-share', `${100 - share}%`);
            const values = document.createElement('div');
            values.className = 'match-statistic-values';
            ['home', 'label', 'away'].forEach((key) => {
                const node = document.createElement(key === 'label' ? 'span' : 'strong');
                node.textContent = String(stat[key] ?? (key === 'label' ? 'İstatistik' : '–'));
                values.append(node);
            });
            const track = document.createElement('div');
            track.className = 'match-statistic-track';
            track.setAttribute('aria-hidden', 'true');
            ['home', 'away'].forEach((side) => {
                const bar = document.createElement('i');
                bar.className = side;
                track.append(bar);
            });
            row.append(values, track);
            return row;
        });
        list.replaceChildren(...rows);
    },

    renderLineups() {
        const list = this.$refs.lineupsList;
        if (!list) return;
        const profileUrl = (player) => {
            if (!player.profile_url) return null;
            try {
                const url = new URL(String(player.profile_url), window.location.origin);
                return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
            } catch { return null; }
        };
        const imageUrl = (player) => {
            if (!player.image) return null;
            try {
                const url = new URL(String(player.image), window.location.origin);
                return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
            } catch { return null; }
        };
        ['home', 'away'].forEach((side) => {
            const target = list.querySelector(`[data-lineup-side="${side}"]`);
            if (!target) return;
            [...target.children].filter((node) => !node.classList.contains('match-lineup-team-heading')).forEach((node) => node.remove());
            const team = this.lineups?.[side] ?? {};
            const starting = Array.isArray(team.starting) ? team.starting : [];
            const subs = Array.isArray(team.subs) ? team.subs : [];
            const formation = this.lineups?.formation?.[side];
            const title = (text) => { const node = document.createElement('h4'); node.textContent = text; target.append(node); };
            const headingFormation = target.querySelector('.match-lineup-team-heading span');
            if (headingFormation) headingFormation.textContent = formation ?? '';
            const rows = this.formationRows(formation, starting);
            if (rows) {
                const pitch = document.createElement('div'); pitch.className = 'match-lineup-pitch';
                rows.forEach((players) => {
                    const row = document.createElement('div'); row.className = 'match-lineup-pitch-row';
                    players.forEach((player) => {
                        const href = profileUrl(player);
                        const card = document.createElement(href ? 'a' : 'div'); card.className = `match-lineup-pitch-player${href ? ' match-lineup-player-link' : ''}`;
                        if (href) { card.href = href; card.setAttribute('aria-label', `${player.name ?? 'Oyuncu'} profiline git`); }
                        const number = document.createElement('span'); number.textContent = String(player.number ?? '—');
                        const name = document.createElement('strong'); name.textContent = String(player.name ?? 'Oyuncu');
                        card.append(number, name);
                        if (player.rating !== undefined) { const rating = document.createElement('small'); rating.className = 'match-player-rating'; rating.textContent = String(player.rating); card.append(rating); }
                        row.append(card);
                    });
                    pitch.append(row);
                });
                target.append(pitch);
            }
            const playerList = (players) => {
                const ordered = document.createElement('ol'); ordered.className = 'match-lineup-list';
                players.forEach((player) => {
                    const item = document.createElement('li');
                    const href = profileUrl(player);
                    const content = document.createElement(href ? 'a' : 'div');
                    if (href) { content.href = href; content.className = 'match-lineup-player-link'; content.setAttribute('aria-label', `${player.name ?? 'Oyuncu'} profiline git`); }
                    const number = document.createElement('span'); number.className = 'match-lineup-number'; number.textContent = String(player.number ?? '—'); content.append(number);
                    const src = imageUrl(player);
                    const imagePlaceholder = () => { const node = document.createElement('span'); node.className = 'match-lineup-image-placeholder'; node.setAttribute('aria-hidden', 'true'); return node; };
                    if (src) {
                        const image = document.createElement('img'); image.src = src; image.alt = ''; image.loading = 'lazy'; image.onerror = () => image.replaceWith(imagePlaceholder()); content.append(image);
                    } else content.append(imagePlaceholder());
                    const info = document.createElement('span'); info.className = 'match-lineup-player-info';
                    const name = document.createElement('strong'); name.textContent = String(player.name ?? 'Oyuncu'); info.append(name);
                    if (player.position_display) { const position = document.createElement('small'); position.textContent = String(player.position_display); info.append(position); }
                    content.append(info);
                    if (player.rating !== undefined) { const rating = document.createElement('small'); rating.className = 'match-player-rating'; rating.textContent = String(player.rating); content.append(rating); }
                    item.append(content);
                    ordered.append(item);
                });
                target.append(ordered);
            };
            title('İlk 11'); playerList(starting);
            if (subs.length) { title('Yedekler'); playerList(subs); }
            if (team.coach?.name) {
                const coach = document.createElement('p'); coach.className = 'match-lineup-coach';
                const label = document.createElement('span'); label.textContent = 'Teknik direktör';
                const name = document.createElement('strong'); name.textContent = String(team.coach.name);
                coach.append(label, name); target.append(coach);
            }
        });
    },

    formationRows(formation, players) {
        if (!Array.isArray(players) || players.length !== 11) return null;
        const value = String(formation ?? '').trim();
        if (!/^\d(?:-?\d){2,4}$/.test(value)) return null;
        const groups = value.includes('-') ? value.split('-') : value.split('');
        const counts = groups.map(Number);
        if (counts.reduce((a, b) => a + b, 0) !== 10 || counts.some((n) => n < 1 || n > 5)) return null;
        if (!['goalkeeper', 'kaleci'].includes(String(players[0]?.position ?? '').toLocaleLowerCase('tr-TR'))) return null;
        const rows = [[players[0]]]; let offset = 1;
        counts.forEach((count) => { rows.push(players.slice(offset, offset + count)); offset += count; });
        const role = (player) => String(player?.position ?? '').trim().toLocaleLowerCase('tr-TR');
        if (rows[1].some((player) => !['defender', 'defans', 'savunma'].includes(role(player)))) return null;
        if (rows.at(-1).some((player) => !['forward', 'forvet'].includes(role(player)))) return null;
        if (rows.slice(2, -1).some((row) => row.some((player) => !['midfielder', 'orta saha', 'forward', 'forvet'].includes(role(player))))) return null;
        return rows;
    },
});

window.todayScoresRibbon = (url, initialMatches, pollingEnabled = false) => ({
    url,
    matches: Array.isArray(initialMatches) ? initialMatches : [],
    pollingEnabled: Boolean(pollingEnabled),
    timer: null,
    wakeTimer: null,

    init() {
        if (this.pollingEnabled && this.hasPollableMatches && !document.hidden) {
            this.start();
        } else {
            this.armWakeup();
        }
    },

    destroy() {
        this.stop();
        if (this.wakeTimer) window.clearTimeout(this.wakeTimer);
    },

    get hasLiveMatches() {
        return this.matches.some((match) => Boolean(match.is_live));
    },

    get hasPollableMatches() {
        return this.matches.some((match) => Boolean(match.polling_active));
    },

    shortName(value) {
        const name = String(value ?? '').trim();

        if (name.length <= 5) return name.toLocaleUpperCase('tr-TR');

        const words = name.split(/\s+/).filter(Boolean);

        if (words.length > 1) {
            return words.map((word) => word[0]).join('').slice(0, 4).toLocaleUpperCase('tr-TR');
        }

        return name.slice(0, 4).toLocaleUpperCase('tr-TR');
    },

    centerText(match) {
        if (match.is_live || match.is_finished || match.home_score !== null || match.away_score !== null) {
            return `${match.home_score ?? '–'} – ${match.away_score ?? '–'}`;
        }

        return match.kickoff_time;
    },

    cardStatus(match) {
        if (!match.is_live || match.status_label === 'DEVRE') return match.status_label;

        return match.status_label === 'CANLI' ? 'CANLI' : `CANLI ${match.status_label}`;
    },

    visibilityChanged() {
        if (document.hidden) {
            this.stop();
        } else if (this.pollingEnabled && this.hasPollableMatches) {
            this.refresh();
            this.start();
        } else {
            this.armWakeup();
        }
    },

    start() {
        if (this.timer || !this.pollingEnabled || !this.hasPollableMatches || document.hidden) return;
        this.timer = window.setInterval(() => this.refresh(), 25000);
    },

    stop() {
        if (this.timer) window.clearInterval(this.timer);
        this.timer = null;
    },

    armWakeup() {
        if (this.wakeTimer || !this.pollingEnabled) return;
        const starts = this.matches
            .filter((match) => !match.is_terminal && !match.polling_active && match.polling_starts_at)
            .map((match) => new Date(match.polling_starts_at).getTime())
            .filter((value) => Number.isFinite(value) && value > Date.now());
        if (starts.length === 0) return;
        const delay = Math.min(...starts) - Date.now();
        this.wakeTimer = window.setTimeout(() => {
            this.wakeTimer = null;
            if (Math.min(...starts) > Date.now()) {
                this.armWakeup();
                return;
            }
            this.matches = this.matches.map((match) => ({
                ...match,
                polling_active: match.polling_active || (!match.is_terminal && new Date(match.polling_starts_at).getTime() <= Date.now()),
            }));
            if (!document.hidden) {
                this.refresh();
                this.start();
            }
        }, Math.min(delay, 2147483647));
    },

    async refresh() {
        if (document.hidden || !this.pollingEnabled || !this.hasPollableMatches) return;

        try {
            const response = await fetch(this.url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) return;

            const payload = await response.json();
            this.matches = Array.isArray(payload.matches) ? payload.matches : this.matches;
            window.dispatchEvent(new CustomEvent('today-scores-updated', { detail: { matches: this.matches } }));

            if (!this.hasPollableMatches) {
                this.stop();
                this.armWakeup();
            }
        } catch (error) {
            // Keep the latest database-backed state visible during transient failures.
        }
    },
});

window.competitionLiveState = (url, initialStates = {}) => ({
    url,
    states: initialStates && typeof initialStates === 'object' ? initialStates : {},
    timer: null,

    init() {
        if (this.pollingIds.length > 0 && !document.hidden) this.start();
    },

    destroy() {
        this.stop();
    },

    get pollingIds() {
        return [...new Set(Object.entries(this.states)
            .filter(([, state]) => Boolean(state?.polling_active) && !state?.is_finished)
            .map(([id]) => Number(id))
            .filter(Number.isInteger))];
    },

    stateFor(id) {
        return this.states[String(id)] ?? this.states[id] ?? null;
    },

    isLive(id, fallback = false) {
        return this.stateFor(id)?.is_live ?? fallback;
    },

    matchPrimary(id, fallback) {
        const state = this.stateFor(id);
        if (!state) return fallback;
        const home = state.score?.home;
        const away = state.score?.away;
        return state.is_live || state.is_finished || home !== null || away !== null ? `${home ?? '–'} - ${away ?? '–'}` : fallback;
    },

    matchMinute(id, fallback = null) {
        const state = this.stateFor(id);
        if (!state) return fallback;
        if (!state.is_live) return null;
        if (state.is_half_time) return 'DEVRE';
        return state.minute !== null ? `${state.minute}′` : null;
    },

    matchStatus(id, fallback) {
        const state = this.stateFor(id);
        if (!state) return fallback;
        if (state.is_live) return state.is_half_time ? null : 'CANLI';
        if (state.is_finished) return state.status === 'finished' ? 'Bitti' : String(state.status_display ?? fallback);
        return String(state.status_display ?? fallback);
    },

    visibilityChanged() {
        if (document.hidden) {
            this.stop();
        } else if (this.pollingIds.length > 0) {
            this.refresh();
            this.start();
        }
    },

    start() {
        if (this.timer || document.hidden || this.pollingIds.length === 0) return;
        this.timer = window.setInterval(() => this.refresh(), 25000);
    },

    stop() {
        if (this.timer) window.clearInterval(this.timer);
        this.timer = null;
    },

    async refresh() {
        const ids = this.pollingIds;
        if (document.hidden || ids.length === 0) {
            this.stop();
            return;
        }

        const query = new URLSearchParams();
        ids.forEach((id) => query.append('ids[]', String(id)));

        try {
            const response = await fetch(`${this.url}?${query}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (payload.matches && typeof payload.matches === 'object') {
                this.states = { ...this.states, ...payload.matches };
            }
            if (this.pollingIds.length === 0) this.stop();
        } catch (error) {
            // Keep the last database-backed state during transient failures.
        }
    },
});

window.liveFixtureHero = (matchId, stateUrl, initial) => ({
    matchId,
    stateUrl,
    timer: null,
    reloading: false,
    ribbonCoverageLost: false,
    homeScore: initial.home_score ?? null,
    awayScore: initial.away_score ?? null,
    heroStatus: initial.status_label ?? 'Canlı',

    init() {
        if (!this.coveredByRibbon() && !document.hidden) this.start();
    },

    destroy() {
        this.stop();
    },

    coveredByRibbon() {
        if (this.ribbonCoverageLost) return false;
        const ribbon = document.querySelector('.today-scores-ribbon[data-polling="on"]');

        return Boolean(ribbon?.dataset.matchIds?.split(',').includes(String(this.matchId)));
    },

    onScoresUpdated(matches) {
        const match = Array.isArray(matches) ? matches.find((item) => item.id === this.matchId) : null;

        if (!match) {
            this.ribbonCoverageLost = true;
            this.start();
            return;
        }

        if (!match.is_live) {
            this.reload();
            return;
        }

        this.homeScore = match.home_score;
        this.awayScore = match.away_score;
        this.heroStatus = match.status_label === 'DEVRE' ? 'Devre Arası' : match.status_label;
    },

    visibilityChanged() {
        if (document.hidden) {
            this.stop();
        } else if (!this.coveredByRibbon()) {
            this.refresh();
            this.start();
        }
    },

    start() {
        if (this.timer || this.reloading || document.hidden) return;
        this.timer = window.setInterval(() => this.refresh(), 30000);
    },

    stop() {
        if (this.timer) window.clearInterval(this.timer);
        this.timer = null;
    },

    reload() {
        if (this.reloading) return;
        this.reloading = true;
        this.stop();
        window.location.reload();
    },

    async refresh() {
        if (document.hidden || this.reloading) return;

        try {
            const response = await fetch(this.stateUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) return;
            const state = await response.json();
            if (!state.is_live) {
                this.reload();
                return;
            }

            this.homeScore = state.score?.home ?? null;
            this.awayScore = state.score?.away ?? null;
            const label = String(state.status_display ?? 'Canlı');
            this.heroStatus = state.is_half_time ? 'Devre Arası' : (state.minute !== null ? `${state.minute}′` : label);
        } catch (error) {
            // Leave the existing hero visible until the database is available again.
        }
    },
});
