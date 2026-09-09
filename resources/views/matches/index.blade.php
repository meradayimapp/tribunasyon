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
        <x-football-match-card :match="$match" />
    @empty
        <div class="empty-state mx-3 mx-md-0">
            <x-ui.icon name="calendar" />
            <strong>Bugün takip edilen organizasyonlarda maç yok.</strong>
            <p>Fikstür verileri son senkronizasyondan sonra burada görünür.</p>
        </div>
    @endforelse
</div>
@endsection
