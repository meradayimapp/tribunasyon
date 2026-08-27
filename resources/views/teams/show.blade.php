@extends('layouts.app')
@section('title', $team->name)
@section('content')
<div class="feed-column mx-auto">
    <section class="team-hero" style="--team-primary:{{ $team->primary_color }}">
        @if($team->cover_image)<img class="team-cover" src="{{ Storage::url($team->cover_image) }}" alt="{{ $team->name }} kapak görseli">@endif
        <div class="team-hero-content">
            <div class="team-identity"><x-team-logo :team="$team" size="xl" /><div><div class="small opacity-75">Takım topluluğu</div><h1>{{ $team->name }}</h1></div></div>
            <livewire:follow-team :team="$team" />
        </div>
    </section>
    <livewire:feed :team="$team" :show-team="false" />
</div>
@endsection
