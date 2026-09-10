<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($pageSeo = $seo ?? app(\App\Services\SeoService::class)->defaults(request(), trim($__env->yieldContent('title'))))
    <x-seo :data="$pageSeo" :site-settings="$siteSettings" />
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
<body class="auth-body">
<div class="auth-shell">
    <header class="auth-header">
        <a class="auth-brand" href="{{ route('home') }}" aria-label="{{ $siteSettings->displayName() }} Akış">
            <x-site-brand :settings="$siteSettings" />
        </a>
        <button class="header-icon-button" type="button" data-theme-toggle aria-label="Açık temaya geç" title="Temayı değiştir">
            <x-ui.icon name="theme" />
        </button>
    </header>

    <main class="auth-main">
        @yield('content')
    </main>
</div>
@livewireScripts
</body>
</html>
