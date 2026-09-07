@extends('layouts.app')
@section('title', 'Oyuncular')
@section('mobile-title', 'Oyuncular')
@section('content')
<div class="panel-shell">
    <div class="d-flex align-items-end justify-content-between gap-3 mb-4"><div><div class="eyebrow">Yönetim</div><h1 class="page-title mb-0">Oyuncular</h1></div><a class="btn btn-primary" href="{{ route('admin.players.create') }}">Oyuncu ekle</a></div>
    <x-panel-nav />
    <form class="surface mb-3" method="GET">
        <div class="row g-2">
            <div class="col-md-5"><label class="form-label" for="player-admin-q">Oyuncu ara</label><input id="player-admin-q" class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Ad veya slug"></div>
            <div class="col-md-3"><label class="form-label" for="player-admin-team">Takım</label><select id="player-admin-team" class="form-select" name="team"><option value="">Tümü</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected((string) request('team') === (string) $team->id)>{{ $team->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="player-admin-status">Durum</label><select id="player-admin-status" class="form-select" name="status"><option value="">Tümü</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="inactive" @selected(request('status') === 'inactive')>Pasif</option></select></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-light w-100" type="submit">Filtrele</button></div>
        </div>
    </form>
    <div class="surface table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Oyuncu</th><th>Takım</th><th>Pozisyon</th><th>Milli takım</th><th>Değer</th><th>Durum</th><th class="text-end">İşlemler</th></tr></thead>
            <tbody>
            @forelse($players as $player)
                <tr>
                    <td><div class="admin-player-identity"><span>@if($player->photoUrl())<img src="{{ $player->photoUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($player->name, 0, 2)) }}@endif</span><div><strong>{{ $player->name }}</strong><small>{{ $player->slug }}</small></div></div></td>
                    <td>{{ $player->currentTeam?->name ?: 'Serbest oyuncu' }}</td><td>{{ $player->position ?: '—' }}</td><td>{{ $player->national_team_name ?: '—' }}</td><td>{{ $player->formatted_market_value ?: '—' }}</td>
                    <td><span class="status-pill {{ ! $player->trashed() && $player->status === \App\Enums\PlayerStatus::Active ? 'live' : '' }}">{{ $player->trashed() ? 'Silinmiş' : ($player->status === \App\Enums\PlayerStatus::Active ? 'Aktif' : 'Pasif') }}</span></td>
                    <td class="text-end">
                        @if($player->trashed())<form class="d-inline" method="POST" action="{{ route('admin.players.restore', $player->id) }}">@csrf<button class="btn btn-sm btn-outline-success" type="submit">Geri al</button></form>
                        @else<a class="btn btn-sm btn-light" href="{{ route('admin.players.edit', $player) }}">Düzenle</a><form class="d-inline" method="POST" action="{{ route('admin.players.destroy', $player) }}" onsubmit="return confirm('Oyuncu silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Sil</button></form>@endif
                    </td>
                </tr>
            @empty<tr><td colspan="7"><div class="empty-state"><x-ui.icon name="person" /><strong>Oyuncu bulunamadı</strong></div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($players->hasPages())<div class="feed-pagination">{{ $players->links() }}</div>@endif
</div>
@endsection
