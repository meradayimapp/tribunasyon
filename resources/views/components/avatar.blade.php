@props(['user', 'size' => 'sm'])
<span class="avatar avatar-{{ $size }}">
    @if($user->avatar_path)
        <img src="{{ Storage::url($user->avatar_path) }}" alt="{{ $user->name }} profil fotoğrafı">
    @else
        {{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}
    @endif
</span>
