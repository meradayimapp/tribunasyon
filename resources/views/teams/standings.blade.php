@extends('layouts.app')
@section('title', $team->name.' Puan Durumu')
@section('mobile-title', $team->name)
@section('mobile-back', route('teams.index'))
@section('content')
<div class="feed-column public-feed standings-page mx-auto">
    @include('teams.partials.profile-header', ['activeTab' => 'standings'])
    <div class="team-tab-content standings-content">
        @if($mappingMissing)
            <div class="empty-state team-tab-empty"><x-ui.icon name="trophy" /><strong>Puan durumu bulunamadı</strong><p>Bu takım için puan durumu henüz mevcut değil.</p></div>
        @elseif($tables->isEmpty())
            <div class="empty-state team-tab-empty"><x-ui.icon name="trophy" /><strong>Puan durumu yüklenemedi</strong><p>Puan durumu şu anda görüntülenemiyor.</p></div>
        @else
            <div class="standings-heading">
                <div><span>{{ $competition->display_name ?: $competition->name }}</span><h2>Puan Durumu</h2></div>
                @if($season)<strong>{{ $season }}</strong>@endif
            </div>
            @include('teams.partials.standings-table')
        @endif
    </div>
</div>
@endsection
