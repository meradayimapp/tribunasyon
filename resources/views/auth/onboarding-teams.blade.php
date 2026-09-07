@extends('layouts.auth')
@section('title', 'Takımlarını seç')
@section('content')
@php
    $checkedTeamIds = array_map('intval', old('team_ids', $selectedTeamIds));
    $checkedFavoriteId = old('favorite_team_id', $favoriteTeamId);
@endphp
<section class="onboarding-card" x-data="{ query: '' }">
    <div class="onboarding-heading">
        <div class="eyebrow">Tribününü oluştur</div>
        <h1 class="auth-title">Takımlarını seç</h1>
        <p class="auth-lead">Takip etmek istediğin toplulukları seç. Daha sonra değiştirebilirsin.</p>
    </div>

    <label class="onboarding-search" for="team-search">
        <x-ui.icon name="search" />
        <input id="team-search" type="search" x-model.debounce.150ms="query" placeholder="Takım ara..." autocomplete="off">
    </label>

    <form method="POST" action="{{ route('onboarding.teams.update') }}">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="onboarding-team-grid">
            @foreach($teams as $team)
                <article class="onboarding-team-card" x-cloak x-show="@js(mb_strtolower($team->name.' '.$team->short_name)).includes(query.toLocaleLowerCase('tr-TR'))">
                    <label class="onboarding-follow-choice">
                        <input class="visually-hidden team-follow-checkbox" type="checkbox" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, $checkedTeamIds, true))>
                        <span class="onboarding-check"><x-ui.icon name="check" /></span>
                        <x-team-logo :team="$team" size="md" />
                        <strong>{{ $team->name }}</strong>
                        <small>{{ $team->short_name }}</small>
                    </label>
                    <label class="onboarding-favorite-choice">
                        <input class="visually-hidden team-favorite-radio" type="radio" name="favorite_team_id" value="{{ $team->id }}" @checked((int) $checkedFavoriteId === $team->id)>
                        <x-ui.icon name="heart" />
                        <span>Favorim</span>
                    </label>
                </article>
            @endforeach
        </div>

        <p class="onboarding-help">Favori takımın otomatik olarak takip listene de eklenir.</p>
        <button class="btn btn-primary auth-primary-action w-100" type="submit">Devam et</button>
    </form>

    <form class="onboarding-skip" method="POST" action="{{ route('onboarding.teams.skip') }}">
        @csrf
        <button type="submit">Şimdilik geç</button>
    </form>
</section>
@endsection
