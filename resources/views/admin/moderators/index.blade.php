@extends('layouts.app')
@section('title', 'Moderatörler · Admin')
@section('content')
<div class="panel-shell"><div class="eyebrow">Yönetim</div><h1 class="page-title">Moderatörler</h1><x-panel-nav /><div class="surface table-responsive"><table class="table mb-0"><thead><tr><th>Moderatör</th><th>Atanan takımlar</th><th></th></tr></thead><tbody>@forelse($moderators as $user)<tr><td><strong>{{ $user->name }}</strong><small class="d-block muted">{{ '@'.$user->username }}</small></td><td>{{ $user->moderatedTeams->pluck('name')->join(', ') ?: 'Atama yok' }}</td><td><a class="btn btn-sm btn-light" href="{{ route('admin.moderators.edit',$user) }}">Atamaları düzenle</a></td></tr>@empty<tr><td colspan="3">Moderatör bulunmuyor.</td></tr>@endforelse</tbody></table></div></div>
@endsection
