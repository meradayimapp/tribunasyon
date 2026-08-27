@extends('layouts.app')
@section('title', 'Moderatör Paneli')
@section('content')
<div class="panel-shell"><div class="eyebrow">Moderatör paneli</div><div class="d-flex justify-content-between align-items-end"><h1 class="page-title">Takımlarım</h1><a class="btn btn-primary mb-3" href="{{ route('moderator.posts.create') }}">Hızlı gönderi</a></div><x-panel-nav type="moderator" /><div class="team-grid">@forelse($teams as $team)<div class="surface"><div class="d-flex gap-3 align-items-center"><x-team-logo :team="$team" size="md" /><div><h2 class="h6 fw-bold mb-1">{{ $team->name }}</h2><span class="small muted">{{ $team->posts_count }} gönderi · {{ $team->followers_count }} takipçi</span></div></div></div>@empty<div class="empty-state">Henüz bir takıma atanmadınız.</div>@endforelse</div></div>
@endsection
