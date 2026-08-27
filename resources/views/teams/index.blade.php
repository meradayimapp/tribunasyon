@extends('layouts.app')
@section('title', 'Takımlar')
@section('content')
<div class="feed-column mx-auto" style="max-width:900px">
    <div class="page-head"><div class="eyebrow">Topluluklar</div><h1 class="page-title">Takımını bul</h1></div>
    <div class="team-grid">
        @foreach($teams as $team)
            <a class="team-tile" href="{{ route('teams.show', $team) }}" style="--team-primary:{{ $team->primary_color }}">
                <div class="team-tile-cover">@if($team->cover_image)<img src="{{ Storage::url($team->cover_image) }}" alt="">@endif</div>
                <div class="team-tile-body"><x-team-logo :team="$team" size="md" /><div><h2 class="h6 fw-bold mb-1">{{ $team->name }}</h2><span class="small muted">{{ $team->followers_count }} takipçi · {{ $team->posts_count }} gönderi</span></div></div>
            </a>
        @endforeach
    </div>
</div>
@endsection
