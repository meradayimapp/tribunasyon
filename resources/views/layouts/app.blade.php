<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Takım topluluklarının sosyal futbol platformu">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="app-shell">
    <aside class="desktop-nav" aria-label="Ana navigasyon">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ config('app.name') }} ana sayfa">
            <span class="brand-mark"><i class="bi bi-activity" aria-hidden="true"></i></span>
            <span>{{ config('app.name') }}</span>
        </a>

        <nav class="side-links">
            <a class="side-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-house{{ request()->routeIs('home') ? '-fill' : '' }}"></i><span>Ana Sayfa</span></a>
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

    <header class="mobile-header">
        @hasSection('mobile-back')
            <a class="mobile-back" href="@yield('mobile-back')" aria-label="Geri dön"><i class="bi bi-chevron-left"></i></a>
        @endif
        <div class="mobile-title">@yield('mobile-title', config('app.name'))</div>
        <span class="mobile-header-spacer" aria-hidden="true"></span>
    </header>

    <main class="app-main">
        @if(session('success'))<div class="flash-wrap"><div class="alert alert-success"><i class="bi bi-check-circle"></i>{{ session('success') }}</div></div>@endif
        @if(session('status'))<div class="flash-wrap"><div class="alert alert-info"><i class="bi bi-info-circle"></i>{{ session('status') }}</div></div>@endif
        @yield('content')
    </main>

    <nav class="mobile-nav" aria-label="Mobil navigasyon">
        <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-house{{ request()->routeIs('home') ? '-fill' : '' }}"></i><span>Ana Sayfa</span></a>
        <a class="{{ request()->routeIs('teams.*') ? 'active' : '' }}" href="{{ route('teams.index') }}"><i class="bi bi-shield{{ request()->routeIs('teams.*') ? '-fill' : '' }}"></i><span>Takımlar</span></a>
        <a class="{{ request()->routeIs('matches.*') ? 'active' : '' }}" href="{{ route('matches.index') }}"><i class="bi bi-dribbble"></i><span>Maçlar</span></a>
        <a class="{{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ auth()->check() ? route('profile.show', auth()->user()) : route('login') }}"><i class="bi bi-person{{ request()->routeIs('profile.*') ? '-fill' : '' }}"></i><span>Profil</span></a>
    </nav>
</div>
@livewireScripts
</body>
</html>
