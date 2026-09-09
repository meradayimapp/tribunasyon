@extends('layouts.app')
@section('title', $team->name)
@section('mobile-title', $team->name)
@section('mobile-back', route('teams.index'))
@section('content')
<div class="feed-column public-feed mx-auto">
    @include('teams.partials.profile-header', ['activeTab' => 'feed'])
    <div class="team-feed-heading"><h2>Akış</h2><span>En yeni paylaşımlar</span></div>
    <livewire:feed :team="$team" :show-team="false" />
</div>
@endsection
