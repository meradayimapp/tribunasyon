@extends('layouts.app')
@section('title', 'Maçlar')
@section('mobile-title', 'Maçlar')
@section('content')
<div class="feed-column mx-auto">
    <div class="page-head"><div class="eyebrow">Mock maç merkezi</div><h1 class="page-title mb-1">Bugünün maçları</h1><p class="muted mb-4">{{ $date->translatedFormat('d F Y, l') }}</p></div>
    @foreach($matches as $match)
        <article class="match-card">
            <div class="match-team"><strong>{{ $match['home'] }}</strong><small class="d-block muted">{{ $match['league'] }}</small></div>
            <div class="match-score">
                @if($match['status'] === 'scheduled')<span>{{ $match['kickoff_at']->format('H:i') }}</span><small class="d-block muted">Başlamadı</small>
                @else<span>{{ $match['home_score'] }} – {{ $match['away_score'] }}</span><small class="d-block {{ $match['status'] === 'live' ? 'text-danger' : 'muted' }}">{{ $match['status'] === 'live' ? 'CANLI' : 'Bitti' }}</small>@endif
            </div>
            <div class="match-team"><strong>{{ $match['away'] }}</strong><small class="d-block muted">{{ $match['kickoff_at']->format('H:i') }}</small></div>
        </article>
    @endforeach
    <p class="small muted mobile-pad mt-3">Bu ekrandaki skorlar geliştirme amaçlı mock veridir.</p>
</div>
@endsection
