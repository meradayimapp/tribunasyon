@extends('layouts.app')
@section('title', $user->name)
@section('mobile-title', 'Profil')
@section('content')
<div class="profile-shell mx-auto">
    <section class="profile-header">
        <x-avatar :user="$user" size="lg" />
        <div class="profile-main">
            <div class="profile-title-row"><div><h1>{{ $user->name }}</h1><span>{{ '@'.$user->username }}</span></div>@auth @if(auth()->id()===$user->id)<a class="profile-edit" href="{{ route('profile.edit') }}">Profili düzenle</a>@endif @endauth</div>
            @if($user->bio)<p class="profile-bio">{{ $user->bio }}</p>@endif
            <p class="profile-date"><i class="bi bi-calendar3"></i>{{ $user->created_at->translatedFormat('F Y') }} tarihinde katıldı</p>
        </div>
    </section>
    @if($user->favoriteTeam)
        <section class="profile-section"><div class="section-heading"><h2>Favori takım</h2></div><a class="favorite-team" href="{{ route('teams.show', $user->favoriteTeam) }}"><x-team-logo :team="$user->favoriteTeam" size="md" /><span><strong>{{ $user->favoriteTeam->name }}</strong><small>Favori takım</small></span><i class="bi bi-chevron-right"></i></a></section>
    @endif
    <section class="profile-section"><div class="section-heading"><h2>Takip ettiği takımlar</h2><span>{{ $user->followedTeams->count() }}</span></div><div class="followed-teams">@forelse($user->followedTeams as $team)<a href="{{ route('teams.show',$team) }}"><x-team-logo :team="$team" size="sm" /><strong>{{ $team->name }}</strong></a>@empty<span class="muted">Henüz takım takip etmiyor.</span>@endforelse</div></section>
</div>
@endsection
