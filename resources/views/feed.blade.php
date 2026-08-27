@extends('layouts.app')
@section('title', 'Ana Sayfa')
@section('content')
<div class="feed-layout">
    <section class="feed-column">
        <div class="page-head"><div class="eyebrow">Senin akışın</div><h1 class="page-title mb-1">Futbol burada konuşulur.</h1><p class="muted mb-4">Takımların en yeni paylaşımları, tek bir akışta.</p></div>
        <livewire:feed />
    </section>
    <aside class="feed-aside">
        <div class="surface aside-card">
            <h2 class="h6 fw-bold mb-1">Takımları keşfet</h2><p class="small muted">Topluluğunu seç, akışını oluştur.</p>
            @foreach($suggestedTeams as $team)
                <a class="team-row" href="{{ route('teams.show', $team) }}"><x-team-logo :team="$team" size="sm" /><span class="flex-grow-1"><strong>{{ $team->name }}</strong><small class="d-block muted">{{ number_format($team->followers_count, 0, ',', '.') }} takipçi</small></span><i class="bi bi-chevron-right muted"></i></a>
            @endforeach
        </div>
    </aside>
</div>
@endsection
