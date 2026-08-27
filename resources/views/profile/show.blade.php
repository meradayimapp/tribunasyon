@extends('layouts.app')
@section('title', $user->name)
@section('content')
<div class="feed-column mx-auto">
    <section class="surface mobile-edge">
        <div class="d-flex align-items-start gap-3"><x-avatar :user="$user" size="lg" /><div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><div><h1 class="h4 fw-bold mb-0">{{ $user->name }}</h1><span class="muted">{{ '@'.$user->username }}</span></div>@auth @if(auth()->id()===$user->id)<a class="btn btn-outline-dark btn-sm" href="{{ route('profile.edit') }}">Profili düzenle</a>@endif @endauth</div>@if($user->bio)<p class="mt-3 mb-1">{{ $user->bio }}</p>@endif<p class="small muted mb-0"><i class="bi bi-calendar3"></i> {{ $user->created_at->translatedFormat('F Y') }} tarihinde katıldı</p></div></div>
    </section>
    <section class="surface mobile-edge mt-3"><h2 class="h6 fw-bold mb-3">Takip ettiği takımlar</h2><div class="d-flex flex-wrap gap-3">@forelse($user->followedTeams as $team)<a class="team-row p-0" href="{{ route('teams.show',$team) }}"><x-team-logo :team="$team" size="sm" /><strong>{{ $team->name }}</strong></a>@empty<span class="muted">Henüz takım takip etmiyor.</span>@endforelse</div></section>
</div>
@endsection
