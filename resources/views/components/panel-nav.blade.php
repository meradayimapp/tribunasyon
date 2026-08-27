@props(['type' => 'admin'])
<nav class="panel-nav" aria-label="Panel navigasyonu">
    @if($type === 'admin')
        <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
        <a class="{{ request()->routeIs('admin.teams.*') ? 'active' : '' }}" href="{{ route('admin.teams.index') }}">Takımlar</a>
        <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Kullanıcılar</a>
        <a class="{{ request()->routeIs('admin.moderators.*') ? 'active' : '' }}" href="{{ route('admin.moderators.index') }}">Moderatörler</a>
        <a class="{{ request()->routeIs('admin.posts.*') ? 'active' : '' }}" href="{{ route('admin.posts.index') }}">Gönderiler</a>
        <a class="{{ request()->routeIs('admin.comments.*') ? 'active' : '' }}" href="{{ route('admin.comments.index') }}">Yorumlar</a>
    @else
        <a class="{{ request()->routeIs('moderator.dashboard') ? 'active' : '' }}" href="{{ route('moderator.dashboard') }}">Takımlarım</a>
        <a class="{{ request()->routeIs('moderator.posts.*') ? 'active' : '' }}" href="{{ route('moderator.posts.index') }}">Gönderiler</a>
        <a class="{{ request()->routeIs('moderator.comments.*') ? 'active' : '' }}" href="{{ route('moderator.comments.index') }}">Yorumlar</a>
    @endif
</nav>
