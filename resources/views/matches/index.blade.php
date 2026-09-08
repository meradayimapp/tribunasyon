@extends('layouts.app')
@section('title', 'Maçlar')
@section('mobile-title', 'Maçlar')
@section('content')
<div class="feed-column mx-auto">
    <div class="page-head">
        <div class="eyebrow">Maç merkezi</div>
        <h1 class="page-title mb-1">Bugünün maçları</h1>
        <p class="muted mb-4">{{ $date->translatedFormat('d F Y, l') }}</p>
    </div>

    @forelse($matches as $match)
        <article class="match-card">
            <div class="match-team">
                <div class="match-team-identity">
                    @if($homeLogo = $match->homeTeam->logoUrl())
                        <img class="match-team-logo" src="{{ $homeLogo }}" alt="" loading="lazy" decoding="async">
                    @endif
                    <strong>{{ $match->homeTeam->resolved_name }}</strong>
                </div>
                <small class="d-block muted">{{ $match->competition->display_name ?: $match->competition->name }}</small>
            </div>
            <div class="match-score">
                @if($match->home_score === null && $match->away_score === null)
                    <span>{{ $match->kickoff_at->setTimezone('Europe/Istanbul')->format('H:i') }}</span>
                @else
                    <span>{{ $match->home_score ?? '–' }} – {{ $match->away_score ?? '–' }}</span>
                @endif
                <small class="d-block {{ $match->is_live ? 'text-danger' : 'muted' }}">{{ $match->statusLabel() }}</small>
            </div>
            <div class="match-team">
                <div class="match-team-identity match-team-identity-away">
                    <strong>{{ $match->awayTeam->resolved_name }}</strong>
                    @if($awayLogo = $match->awayTeam->logoUrl())
                        <img class="match-team-logo" src="{{ $awayLogo }}" alt="" loading="lazy" decoding="async">
                    @endif
                </div>
                <small class="d-block muted">{{ $match->kickoff_at->setTimezone('Europe/Istanbul')->format('H:i') }}</small>
            </div>
        </article>
    @empty
        <div class="empty-state mx-3 mx-md-0">
            <x-ui.icon name="calendar" />
            <strong>Bugün takip edilen organizasyonlarda maç yok.</strong>
            <p>Fikstür verileri son senkronizasyondan sonra burada görünür.</p>
        </div>
    @endforelse
</div>
@endsection
