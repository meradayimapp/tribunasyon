@props(['user', 'size' => 'sm'])
<span class="avatar avatar-{{ $size }}">
    @if($user->displayAvatarUrl())
        <img src="{{ $user->displayAvatarUrl() }}" alt="{{ $user->displayName() }} profil fotoğrafı">
    @else
        {{ $user->initials() }}
    @endif
</span>
