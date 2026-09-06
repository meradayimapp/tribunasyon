<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Takım topluluklarının sosyal futbol platformu">
    <title>@hasSection('title')@yield('title') · @endif{{ $siteSettings->displayName() }}</title>
    @if($siteSettings->mediaUrl('favicon_path'))
        <link rel="icon" href="{{ $siteSettings->mediaUrl('favicon_path') }}">
    @endif
    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('tribun-theme');
                document.documentElement.dataset.bsTheme = savedTheme === 'light' ? 'light' : 'dark';
            } catch (error) {
                document.documentElement.dataset.bsTheme = 'dark';
            }
        })();
    </script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="app-shell" x-data="appShell()" @scroll.window.passive="onScroll()" @resize.window.debounce.100ms="resetHeader()" x-on:livewire:navigated.window="resetHeader()" @keydown.escape.window="mobileMenuOpen = false" x-effect="document.body.classList.toggle('mobile-menu-open', mobileMenuOpen); if (mobileMenuOpen) headerHidden = false">
    <aside class="desktop-nav" aria-label="Ana navigasyon">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $siteSettings->displayName() }} Akış">
            <x-site-brand :settings="$siteSettings" />
        </a>

        <nav class="side-links">
            <a class="side-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><x-ui.icon name="home" /><span>Akış</span></a>
            <a class="side-link {{ request()->routeIs('teams.*') ? 'active' : '' }}" href="{{ route('teams.index') }}"><x-ui.icon name="teams" /><span>Takımlar</span></a>
            <a class="side-link {{ request()->routeIs('matches.*') ? 'active' : '' }}" href="{{ route('matches.index') }}"><x-ui.icon name="football" /><span>Maçlar</span></a>
            @auth
                <a class="side-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show', auth()->user()) }}"><x-ui.icon name="person" /><span>Profil</span></a>
                @if(auth()->user()->isAdmin())
                    <a class="side-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><x-ui.icon name="dashboard" /><span>Admin</span></a>
                @elseif(auth()->user()->isModerator())
                    <a class="side-link {{ request()->routeIs('moderator.*') ? 'active' : '' }}" href="{{ route('moderator.dashboard') }}"><x-ui.icon name="edit" /><span>Moderatör</span></a>
                @endif
            @endauth
        </nav>

        <div class="nav-user">
            @auth
                <a class="nav-user-profile" href="{{ route('profile.show', auth()->user()) }}">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <span><strong>{{ auth()->user()->name }}</strong><small>{{ '@'.auth()->user()->username }}</small></span>
                </a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-logout" type="submit"><x-ui.icon name="logout" /> Çıkış yap</button></form>
            @else
                <a class="btn btn-primary w-100 mb-2" href="{{ route('register') }}">Aramıza katıl</a>
                <a class="btn btn-light w-100" href="{{ route('login') }}">Giriş yap</a>
            @endauth
        </div>
    </aside>

    <header class="app-header" x-ref="header" :class="{ 'app-header--hidden': headerHidden }" @focusin="resetHeader()">
        <div class="desktop-header-row">
            <livewire:header-search />
            <div class="header-actions">
                <button class="header-icon-button" type="button" data-theme-toggle aria-label="Açık temaya geç" title="Temayı değiştir">
                    <x-ui.icon name="theme" />
                </button>
                @auth
                    <a class="header-icon-button" href="{{ route('profile.show', auth()->user()) }}" aria-label="Profili aç"><x-ui.icon name="person" /></a>
                @else
                    <a class="header-login" href="{{ route('login') }}">Giriş yap</a>
                @endauth
            </div>
        </div>

        <div class="mobile-header-row">
            <div class="mobile-context">
                @hasSection('mobile-back')
                    <a class="mobile-back" href="@yield('mobile-back')" aria-label="Geri dön"><x-ui.icon name="chevron-left" /></a>
                @endif
                @if(request()->routeIs('home') && ! View::hasSection('mobile-back'))
                    <x-site-brand :settings="$siteSettings" variant="mobile" />
                @else
                    <div class="mobile-title">@yield('mobile-title', $siteSettings->displayName())</div>
                @endif
            </div>
            <div class="mobile-header-actions">
                <a class="header-icon-button {{ request()->routeIs('search.index') ? 'active' : '' }}" href="{{ route('search.index') }}" aria-label="Ara"><x-ui.icon name="search" /></a>
                <button class="header-icon-button" type="button" data-theme-toggle aria-label="Açık temaya geç" title="Temayı değiştir">
                    <x-ui.icon name="theme" />
                </button>
                @auth
                    <button class="header-icon-button mobile-menu-trigger" type="button" @click="mobileMenuOpen = true" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-account-menu" aria-label="Hesap menüsünü aç">
                        <x-ui.icon name="menu" />
                    </button>
                @endauth
            </div>
        </div>
    </header>

    @auth
        <div class="mobile-menu-backdrop" x-cloak x-show="mobileMenuOpen" x-transition.opacity @click="mobileMenuOpen = false" aria-hidden="true"></div>
        <aside id="mobile-account-menu" class="mobile-account-menu" x-cloak x-show="mobileMenuOpen" x-transition:enter="mobile-menu-enter" x-transition:enter-start="mobile-menu-enter-start" x-transition:enter-end="mobile-menu-enter-end" x-transition:leave="mobile-menu-leave" x-transition:leave-start="mobile-menu-leave-start" x-transition:leave-end="mobile-menu-leave-end" role="dialog" aria-modal="true" aria-label="Hesap ve yönetim menüsü">
            <div class="mobile-menu-head">
                <a href="{{ route('profile.show', auth()->user()) }}" class="mobile-menu-user" @click="mobileMenuOpen = false">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <span><strong>{{ auth()->user()->name }}</strong><small>{{ '@'.auth()->user()->username }}</small></span>
                </a>
                <button type="button" class="header-icon-button" @click="mobileMenuOpen = false" aria-label="Menüyü kapat"><x-ui.icon name="close" /></button>
            </div>

            <nav class="mobile-menu-links" aria-label="Hesap menüsü">
                <a class="{{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show', auth()->user()) }}" @click="mobileMenuOpen = false"><x-ui.icon name="person" /><span>Profil</span></a>

                @if(auth()->user()->isAdmin())
                    <div class="mobile-menu-label">Yönetim</div>
                    <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" @click="mobileMenuOpen = false"><x-ui.icon name="dashboard" /><span>Admin Paneli</span></a>
                    <a class="{{ request()->routeIs('admin.teams.*') ? 'active' : '' }}" href="{{ route('admin.teams.index') }}" @click="mobileMenuOpen = false"><x-ui.icon name="teams" /><span>Takımlar</span></a>
                    <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}" @click="mobileMenuOpen = false"><x-ui.icon name="people" /><span>Kullanıcılar</span></a>
                    <a class="{{ request()->routeIs('admin.moderators.*') ? 'active' : '' }}" href="{{ route('admin.moderators.index') }}" @click="mobileMenuOpen = false"><x-ui.icon name="check-circle" /><span>Moderatörler</span></a>
                    <a class="{{ request()->routeIs('admin.posts.*') ? 'active' : '' }}" href="{{ route('admin.posts.index') }}" @click="mobileMenuOpen = false"><x-ui.icon name="image" /><span>Gönderiler</span></a>
                    <a class="{{ request()->routeIs('admin.comments.*') ? 'active' : '' }}" href="{{ route('admin.comments.index') }}" @click="mobileMenuOpen = false"><x-ui.icon name="comment" /><span>Yorumlar</span></a>
                    <a class="{{ request()->routeIs('admin.organizations.*') ? 'active' : '' }}" href="{{ route('admin.organizations.index') }}" @click="mobileMenuOpen = false"><x-ui.icon name="trophy" /><span>Organizasyonlar</span></a>
                    <a class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}" @click="mobileMenuOpen = false"><x-ui.icon name="settings" /><span>Site Ayarları</span></a>
                @elseif(auth()->user()->isModerator())
                    <div class="mobile-menu-label">Moderatör</div>
                    <a class="{{ request()->routeIs('moderator.dashboard') ? 'active' : '' }}" href="{{ route('moderator.dashboard') }}" @click="mobileMenuOpen = false"><x-ui.icon name="edit" /><span>Moderatör Paneli</span></a>
                    <a class="{{ request()->routeIs('moderator.posts.*') ? 'active' : '' }}" href="{{ route('moderator.posts.index') }}" @click="mobileMenuOpen = false"><x-ui.icon name="image" /><span>Gönderilerim</span></a>
                @endif

                <div class="mobile-menu-label">Tercihler</div>
                <button type="button" data-theme-toggle @click="mobileMenuOpen = false"><x-ui.icon name="theme" /><span>Tema değiştir</span></button>
            </nav>

            <form class="mobile-menu-logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><x-ui.icon name="logout" /><span>Çıkış</span></button>
            </form>
        </aside>
    @endauth

    <main class="app-main">
        @if(session('success'))<div class="flash-wrap"><div class="alert alert-success"><x-ui.icon name="check-circle" />{{ session('success') }}</div></div>@endif
        @if(session('status'))<div class="flash-wrap"><div class="alert alert-info"><x-ui.icon name="info" />{{ session('status') }}</div></div>@endif
        @yield('content')
    </main>

    <nav class="mobile-nav" aria-label="Mobil navigasyon">
        <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><x-ui.icon name="home" /><span>Akış</span></a>
        <a class="{{ request()->routeIs('teams.*') ? 'active' : '' }}" href="{{ route('teams.index') }}"><x-ui.icon name="teams" /><span>Takımlar</span></a>
        <a class="{{ request()->routeIs('matches.*') ? 'active' : '' }}" href="{{ route('matches.index') }}"><x-ui.icon name="football" /><span>Maçlar</span></a>
        <a class="{{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ auth()->check() ? route('profile.show', auth()->user()) : route('login') }}"><x-ui.icon name="person" /><span>Profil</span></a>
    </nav>
</div>
@livewireScripts
</body>
</html>
