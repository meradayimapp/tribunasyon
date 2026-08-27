@extends('layouts.app')
@section('title', $team->name)
@section('mobile-title', $team->name)
@section('mobile-back', route('teams.index'))
@section('content')
<div class="feed-column public-feed mx-auto">
    <section class="team-hero" style="--team-primary:{{ $team->primary_color }}">
        @if($team->cover_image)<img class="team-cover" src="{{ Storage::url($team->cover_image) }}" alt="{{ $team->name }} kapak görseli" loading="eager" decoding="async">@endif
        <div class="team-hero-content">
            <div class="team-identity"><x-team-logo :team="$team" size="xl" /><div><span>Takım topluluğu</span><h1>{{ $team->name }}</h1></div></div>
            <livewire:follow-team :team="$team" />
        </div>
    </section>
    <div class="team-feed-heading"><h2>Akış</h2><span>En yeni paylaşımlar</span></div>
    <livewire:feed :team="$team" :show-team="false" />
</div>
@endsection
