@extends('layouts.app')
@section('title', 'Akış')
@section('content')
<div class="feed-layout">
    <section class="feed-column">
        <div class="page-head feed-intro"><div class="eyebrow">Senin akışın</div><h1 class="page-title">Futbol burada konuşulur.</h1><p>Takımların en yeni paylaşımları, tek bir akışta.</p></div>
        <livewire:feed />
    </section>
    <aside class="feed-aside">
        <div class="aside-card">
            <div class="aside-heading"><span>Topluluklar</span><a href="{{ route('teams.index') }}">Tümünü gör</a></div>
            <div class="community-team-list">
                @foreach($suggestedTeams as $team)
                    <a class="team-row" href="{{ route('teams.show', $team) }}"><x-team-logo :team="$team" size="xs" /><span class="flex-grow-1"><strong>{{ $team->name }}</strong><small class="d-block muted">{{ number_format($team->followers_count, 0, ',', '.') }} takipçi</small></span><x-ui.icon name="chevron-right" class="muted" /></a>
                @endforeach
            </div>
        </div>
    </aside>
</div>
@endsection
