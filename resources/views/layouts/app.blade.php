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
<div class="app-shell" x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false" x-effect="document.body.classList.toggle('mobile-menu-open', mobileMenuOpen)">
    <aside class="desktop-nav" aria-label="Ana navigasyon">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $siteSettings->displayName() }} Akış">
            <x-site-brand :settings="$siteSettings" />
        </a>

        <nav class="side-links">
            <a class="side-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-house{{ request()->routeIs('home') ? '-fill' : '' }}"></i><span>Akış</span></a>
            <a class="side-link {{ request()->routeIs('teams.*') ? 'active' : '' }}" href="{{ route('teams.index') }}"><i class="bi bi-shield{{ request()->routeIs('teams.*') ? '-fill' : '' }}"></i><span>Takımlar</span></a>
            <a class="side-link {{ request()->routeIs('matches.*') ? 'active' : '' }}" href="{{ route('matches.index') }}"><i class="bi bi-dribbble"></i><span>Maçlar</span></a>
            @auth
                <a class="side-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show', auth()->user()) }}"><i class="bi bi-person{{ request()->routeIs('profile.*') ? '-fill' : '' }}"></i><span>Profil</span></a>
                @if(auth()->user()->isAdmin())
                    <a class="side-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid"></i><span>Admin</span></a>
                @elseif(auth()->user()->isModerator())
                    <a class="side-link {{ request()->routeIs('moderator.*') ? 'active' : '' }}" href="{{ route('moderator.dashboard') }}"><i class="bi bi-pencil-square"></i><span>Moderatör</span></a>
                @endif
            @endauth
        </nav>

        <div class="nav-user">
            @auth
                <a class="nav-user-profile" href="{{ route('profile.show', auth()->user()) }}">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <span><strong>{{ auth()->user()->name }}</strong><small>{{ '@'.auth()->user()->username }}</small></span>
                </a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-logout" type="submit"><i class="bi bi-box-arrow-right"></i> Çıkış yap</button></form>
            @else
                <a class="btn btn-primary w-100 mb-2" href="{{ route('register') }}">Aramıza katıl</a>
                <a class="btn btn-light w-100" href="{{ route('login') }}">Giriş yap</a>
            @endauth
        </div>
    </aside>

    <header class="app-header">
        <div class="desktop-header-row">
            <livewire:header-search />
            <div class="header-actions">
                <button class="header-icon-button" type="button" data-theme-toggle aria-label="Açık temaya geç" title="Temayı değiştir">
                    <i class="bi bi-sun" data-theme-icon aria-hidden="true"></i>
                </button>
                @auth
                    <a class="header-icon-button" href="{{ route('profile.show', auth()->user()) }}" aria-label="Profili aç"><i class="bi bi-person" aria-hidden="true"></i></a>
                @else
                    <a class="header-login" href="{{ route('login') }}">Giriş yap</a>
                @endauth
            </div>
        </div>

        <div class="mobile-header-row">
            <div class="mobile-context">
                @hasSection('mobile-back')
                    <a class="mobile-back" href="@yield('mobile-back')" aria-label="Geri dön"><i class="bi bi-chevron-left"></i></a>
                @endif
                @if(request()->routeIs('home') && ! View::hasSection('mobile-back'))
                    <x-site-brand :settings="$siteSettings" variant="mobile" />
                @else
                    <div class="mobile-title">@yield('mobile-title', $siteSettings->displayName())</div>
                @endif
            </div>
            <div class="mobile-header-actions">
                <a class="header-icon-button {{ request()->routeIs('search.index') ? 'active' : '' }}" href="{{ route('search.index') }}" aria-label="Ara"><i class="bi bi-search" aria-hidden="true"></i></a>
                <button class="header-icon-button" type="button" data-theme-toggle aria-label="Açık temaya geç" title="Temayı değiştir">
                    <i class="bi bi-sun" data-theme-icon aria-hidden="true"></i>
                </button>
                @auth
                    <button class="header-icon-button mobile-menu-trigger" type="button" @click="mobileMenuOpen = true" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-account-menu" aria-label="Hesap menüsünü aç">
                        <i class="bi bi-list" aria-hidden="true"></i>
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
                <button type="button" class="header-icon-button" @click="mobileMenuOpen = false" aria-label="Menüyü kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>

            <nav class="mobile-menu-links" aria-label="Hesap menüsü">
                <a href="{{ route('profile.show', auth()->user()) }}" @click="mobileMenuOpen = false"><i class="bi bi-person"></i><span>Profil</span></a>

                @if(auth()->user()->isAdmin())
                    <div class="mobile-menu-label">Yönetim</div>
                    <a href="{{ route('admin.dashboard') }}" @click="mobileMenuOpen = false"><i class="bi bi-grid"></i><span>Admin Paneli</span></a>
                    <a href="{{ route('admin.teams.index') }}" @click="mobileMenuOpen = false"><i class="bi bi-shield"></i><span>Takımlar</span></a>
                    <a href="{{ route('admin.users.index') }}" @click="mobileMenuOpen = false"><i class="bi bi-people"></i><span>Kullanıcılar</span></a>
                    <a href="{{ route('admin.moderators.index') }}" @click="mobileMenuOpen = false"><i class="bi bi-person-check"></i><span>Moderatörler</span></a>
                    <a href="{{ route('admin.posts.index') }}" @click="mobileMenuOpen = false"><i class="bi bi-images"></i><span>Gönderiler</span></a>
                    <a href="{{ route('admin.comments.index') }}" @click="mobileMenuOpen = false"><i class="bi bi-chat"></i><span>Yorumlar</span></a>
                    <a href="{{ route('admin.organizations.index') }}" @click="mobileMenuOpen = false"><i class="bi bi-trophy"></i><span>Organizasyonlar</span></a>
                    <a href="{{ route('admin.settings.edit') }}" @click="mobileMenuOpen = false"><i class="bi bi-gear"></i><span>Site Ayarları</span></a>
                @elseif(auth()->user()->isModerator())
                    <div class="mobile-menu-label">Moderatör</div>
                    <a href="{{ route('moderator.dashboard') }}" @click="mobileMenuOpen = false"><i class="bi bi-pencil-square"></i><span>Moderatör Paneli</span></a>
                    <a href="{{ route('moderator.posts.index') }}" @click="mobileMenuOpen = false"><i class="bi bi-images"></i><span>Gönderilerim</span></a>
                @endif

                <div class="mobile-menu-label">Tercihler</div>
                <button type="button" data-theme-toggle @click="mobileMenuOpen = false"><i class="bi bi-sun" data-theme-icon aria-hidden="true"></i><span>Tema değiştir</span></button>
            </nav>

            <form class="mobile-menu-logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><i class="bi bi-box-arrow-right"></i><span>Çıkış</span></button>
            </form>
        </aside>
    @endauth

    <main class="app-main">
        @if(session('success'))<div class="flash-wrap"><div class="alert alert-success"><i class="bi bi-check-circle"></i>{{ session('success') }}</div></div>@endif
        @if(session('status'))<div class="flash-wrap"><div class="alert alert-info"><i class="bi bi-info-circle"></i>{{ session('status') }}</div></div>@endif
        @yield('content')
    </main>

    <nav class="mobile-nav" aria-label="Mobil navigasyon">
        <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-house{{ request()->routeIs('home') ? '-fill' : '' }}"></i><span>Akış</span></a>
        <a class="{{ request()->routeIs('teams.*') ? 'active' : '' }}" href="{{ route('teams.index') }}"><i class="bi bi-shield{{ request()->routeIs('teams.*') ? '-fill' : '' }}"></i><span>Takımlar</span></a>
        <a class="{{ request()->routeIs('matches.*') ? 'active' : '' }}" href="{{ route('matches.index') }}"><i class="bi bi-dribbble"></i><span>Maçlar</span></a>
        <a class="{{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ auth()->check() ? route('profile.show', auth()->user()) : route('login') }}"><i class="bi bi-person{{ request()->routeIs('profile.*') ? '-fill' : '' }}"></i><span>Profil</span></a>
    </nav>
</div>
@livewireScripts
</body>
</html>
