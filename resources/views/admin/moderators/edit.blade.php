@extends('layouts.app')
@section('title', 'Moderatör ataması')
@section('content')
<div class="panel-shell"><div class="eyebrow">Moderatör</div><h1 class="page-title">{{ $user->name }}</h1><x-panel-nav /><form class="surface" method="POST" action="{{ route('admin.moderators.update',$user) }}">@csrf @method('PUT')<h2 class="h6 fw-bold">Yetkili olduğu takımlar</h2>@foreach($teams as $team)<div class="form-check py-2"><input class="form-check-input" type="checkbox" name="teams[]" value="{{ $team->id }}" id="team-{{ $team->id }}" @checked($user->moderatedTeams->contains($team))><label class="form-check-label" for="team-{{ $team->id }}">{{ $team->name }}</label></div>@endforeach<button class="btn btn-primary mt-3">Atamaları kaydet</button></form></div>
@endsection
