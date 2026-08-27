<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Takım topluluklarının sosyal futbol platformu">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="app-shell">
    <aside class="desktop-nav" aria-label="Ana navigasyon">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i class="bi bi-activity"></i></span>{{ config('app.name') }}</a>
        <nav class="side-links">
            <a class="side-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-house"></i>Ana Sayfa</a>
            <a class="side-link {{ request()->routeIs('teams.*') ? 'active' : '' }}" href="{{ route('teams.index') }}"><i class="bi bi-shield"></i>Takımlar</a>
            <a class="side-link {{ request()->routeIs('matches.*') ? 'active' : '' }}" href="{{ route('matches.index') }}"><i class="bi bi-dribbble"></i>Maçlar</a>
            @auth
                <a class="side-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show', auth()->user()) }}"><i class="bi bi-person"></i>Profil</a>
                @if(auth()->user()->isAdmin())
                    <a class="side-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid"></i>Admin</a>
                @elseif(auth()->user()->isModerator())
                    <a class="side-link {{ request()->routeIs('moderator.*') ? 'active' : '' }}" href="{{ route('moderator.dashboard') }}"><i class="bi bi-pencil-square"></i>Moderatör</a>
                @endif
            @endauth
        </nav>
        <div class="nav-user">
            @auth
                <div class="d-flex align-items-center gap-2 mb-2">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <div class="small lh-sm"><strong>{{ auth()->user()->name }}</strong><br><span class="muted">{{ '@'.auth()->user()->username }}</span></div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-light w-100" type="submit">Çıkış yap</button></form>
            @else
                <a class="btn btn-primary w-100 mb-2" href="{{ route('register') }}">Aramıza katıl</a>
                <a class="btn btn-light w-100" href="{{ route('login') }}">Giriş yap</a>
            @endauth
        </div>
    </aside>

    <main class="app-main">
        @if(session('success'))<div class="container-fluid px-md-4"><div class="alert alert-success">{{ session('success') }}</div></div>@endif
        @if(session('status'))<div class="container-fluid px-md-4"><div class="alert alert-info">{{ session('status') }}</div></div>@endif
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
