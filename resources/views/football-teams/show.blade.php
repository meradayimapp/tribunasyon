@extends('layouts.app')
@section('title', $footballTeam->resolved_name)
@section('mobile-title', $footballTeam->resolved_name)
@section('mobile-back', route('matches.index'))
@section('content')
<div class="feed-column mx-auto">
    <section class="surface mobile-edge football-team-hero">
        @if($logo = $footballTeam->logoUrl())<img src="{{ $logo }}" alt="{{ $footballTeam->resolved_name }} logosu">@endif
        <div><span class="eyebrow">Futbol takımı</span><h1 class="page-title">{{ $footballTeam->resolved_name }}</h1>@if($footballTeam->country)<p class="muted mb-0">{{ $footballTeam->country }}</p>@endif</div>
    </section>

    @if($competitions->isNotEmpty())
        <section class="surface mobile-edge mt-3"><h2 class="h6">Organizasyonlar</h2><div class="football-team-competitions">@foreach($competitions as $competition)<span>{{ $competition->display_name ?: $competition->name }}</span>@endforeach</div></section>
    @endif

    <section class="mt-4"><h2 class="h6 px-3 px-md-0">Yaklaşan maçlar</h2>@forelse($upcoming as $match)<x-football-match-card :match="$match" />@empty<div class="surface mobile-edge muted">Planlanmış maç bulunmuyor.</div>@endforelse</section>
    <section class="mt-4"><h2 class="h6 px-3 px-md-0">Son maçlar</h2>@forelse($recent as $match)<x-football-match-card :match="$match" />@empty<div class="surface mobile-edge muted">Geçmiş maç bulunmuyor.</div>@endforelse</section>
</div>
@endsection
