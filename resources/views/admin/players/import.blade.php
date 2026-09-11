@extends('layouts.app')
@section('title', "API'den Kadro Aktar")
@section('mobile-title', 'Kadro Aktar')
@section('content')
<div class="panel-shell">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div><div class="eyebrow">Oyuncu yönetimi</div><h1 class="page-title mb-0">API'den Kadro Aktar</h1></div>
        <a class="btn btn-light" href="{{ route('admin.players.index') }}">Oyunculara dön</a>
    </div>
    <x-panel-nav />

    @if($previewError)<div class="alert alert-warning">{{ $previewError }}</div>@endif
    @if($errors->has('import'))<div class="alert alert-danger">{{ $errors->first('import') }}</div>@endif

    <section class="surface mb-4">
        <h2 class="h6 fw-bold mb-3">1. Takım seç</h2>
        <form method="POST" action="{{ route('admin.players.import.preview') }}">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label" for="squad-team">Football API'ye bağlı takım</label>
                    <select id="squad-team" class="form-select @error('team_id') is-invalid @enderror" name="team_id" required>
                        <option value="">Takım seçin</option>
                        @foreach($teams as $team)<option value="{{ $team->id }}" @selected((string) old('team_id', $selectedTeamId) === (string) $team->id)>{{ $team->name }}</option>@endforeach
                    </select>
                    @error('team_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4"><button class="btn btn-primary w-100" type="submit">Kadroyu Getir</button></div>
            </div>
        </form>
        @if($teams->isEmpty())<p class="muted mb-0 mt-3">Football API ile eşleştirilmiş aktif takım bulunmuyor.</p>@endif
    </section>

    @if($preview)
        <section class="surface mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="eyebrow">{{ $preview['team']->name }} API kadrosu</div>
                    <h2 class="h4 mb-1">{{ $preview['total'] }} oyuncu bulundu</h2>
                    <p class="muted mb-0">@if($preview['season'])Sezon: {{ $preview['season'] }} · @endif Önizleme 10 dakika geçerlidir.</p>
                </div>
                <span class="badge text-bg-info">Veritabanı henüz değiştirilmedi</span>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.players.import.apply') }}">
            @csrf
            <input type="hidden" name="team_id" value="{{ $preview['team']->id }}">
            <input type="hidden" name="token" value="{{ $preview['token'] }}">
            @php($rowIndex = 0)

            @if($preview['groups']['new'] !== [])
                <section class="surface mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3"><h2 class="h6 fw-bold mb-0">Yeni oyuncular</h2><span class="badge text-bg-primary">{{ count($preview['groups']['new']) }}</span></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Seç</th><th>Oyuncu</th><th>Pozisyon</th><th>Forma</th><th>Ülke / uyruk</th><th>İşlem</th><th>Yerel oyuncu</th></tr></thead>
                            <tbody>
                            @foreach($preview['groups']['new'] as $apiPlayer)
                                <tr>
                                    <td><input type="hidden" name="players[{{ $rowIndex }}][selected]" value="0"><input class="form-check-input" type="checkbox" name="players[{{ $rowIndex }}][selected]" value="1" @checked((string) old("players.$rowIndex.selected", '1') === '1')></td>
                                    <td><input type="hidden" name="players[{{ $rowIndex }}][provider_player_id]" value="{{ $apiPlayer['provider_player_id'] }}"><strong>{{ $apiPlayer['name'] }}</strong><small class="d-block muted">{{ $apiPlayer['provider_player_id'] }}</small><span class="badge text-bg-primary mt-1">Yeni</span></td>
                                    <td>{{ $apiPlayer['position'] ?: (($apiPlayer['position_requires_manual_edit'] ?? false) ? 'Manuel düzenlenecek' : '—') }}</td><td>{{ $apiPlayer['shirt_number'] !== null ? '#'.$apiPlayer['shirt_number'] : '—' }}</td><td>{{ $apiPlayer['nationality'] ?: '—' }}</td>
                                    <td><select class="form-select form-select-sm" name="players[{{ $rowIndex }}][action]"><option value="create" @selected(old("players.$rowIndex.action", 'create') === 'create')>Yeni oluştur</option><option value="map" @selected(old("players.$rowIndex.action") === 'map')>Mevcutla eşleştir</option></select></td>
                                    <td><select class="form-select form-select-sm" name="players[{{ $rowIndex }}][local_player_id]"><option value="">Oyuncu seçin</option>@foreach($preview['manual_candidates'] as $candidate)<option value="{{ $candidate->id }}" @selected((string) old("players.$rowIndex.local_player_id") === (string) $candidate->id)>{{ $candidate->name }}</option>@endforeach</select></td>
                                </tr>
                                @php($rowIndex++)
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($preview['groups']['existing_mapped'] !== [])
                <section class="surface mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3"><h2 class="h6 fw-bold mb-0">Mevcut / eşleşmiş</h2><span class="badge text-bg-success">{{ count($preview['groups']['existing_mapped']) }}</span></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Seç</th><th>API oyuncusu</th><th>Yerel kayıt</th><th>Pozisyon</th><th>Forma</th><th>Ülke / uyruk</th><th>Durum</th></tr></thead>
                            <tbody>
                            @foreach($preview['groups']['existing_mapped'] as $apiPlayer)
                                <tr>
                                    <td><input type="hidden" name="players[{{ $rowIndex }}][selected]" value="0"><input class="form-check-input" type="checkbox" name="players[{{ $rowIndex }}][selected]" value="1" @checked((string) old("players.$rowIndex.selected", '1') === '1')></td>
                                    <td><input type="hidden" name="players[{{ $rowIndex }}][provider_player_id]" value="{{ $apiPlayer['provider_player_id'] }}"><input type="hidden" name="players[{{ $rowIndex }}][action]" value="sync"><strong>{{ $apiPlayer['name'] }}</strong><small class="d-block muted">{{ $apiPlayer['provider_player_id'] }}</small></td>
                                    <td>{{ $apiPlayer['local_player_name'] }}<small class="d-block muted">/{{ $apiPlayer['local_player_slug'] }}</small></td><td>{{ $apiPlayer['position'] ?: (($apiPlayer['position_requires_manual_edit'] ?? false) ? 'Manuel düzenlenecek' : '—') }}</td><td>{{ $apiPlayer['shirt_number'] !== null ? '#'.$apiPlayer['shirt_number'] : '—' }}</td><td>{{ $apiPlayer['nationality'] ?: '—' }}</td>
                                    <td><span class="badge {{ $apiPlayer['local_player_deleted'] ? 'text-bg-secondary' : 'text-bg-success' }}">{{ $apiPlayer['local_player_deleted'] ? 'Mevcut · silinmiş' : 'Mevcut' }}</span></td>
                                </tr>
                                @php($rowIndex++)
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($preview['groups']['needs_manual_mapping'] !== [])
                <section class="surface mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2"><h2 class="h6 fw-bold mb-0">Manuel eşleştirme gerekiyor</h2><span class="badge text-bg-warning">{{ count($preview['groups']['needs_manual_mapping']) }}</span></div>
                    <p class="muted">Ad benzerliği yalnız yardımcı bilgi olarak gösterilir. Eşleştirme otomatik yapılmaz.</p>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Seç</th><th>API oyuncusu</th><th>Öneri</th><th>İşlem</th><th>Yerel oyuncu</th></tr></thead>
                            <tbody>
                            @foreach($preview['groups']['needs_manual_mapping'] as $apiPlayer)
                                <tr>
                                    <td><input type="hidden" name="players[{{ $rowIndex }}][selected]" value="0"><input class="form-check-input" type="checkbox" name="players[{{ $rowIndex }}][selected]" value="1" @checked((string) old("players.$rowIndex.selected", '0') === '1')></td>
                                    <td><input type="hidden" name="players[{{ $rowIndex }}][provider_player_id]" value="{{ $apiPlayer['provider_player_id'] }}"><strong>{{ $apiPlayer['name'] }}</strong><small class="d-block muted">{{ $apiPlayer['provider_player_id'] }}</small><span class="badge text-bg-warning mt-1">Eşleştirme gerekli</span></td>
                                    <td>@foreach($apiPlayer['suggestions'] as $suggestion)<span class="d-block">{{ $suggestion['name'] }}</span>@endforeach</td>
                                    <td><select class="form-select form-select-sm" name="players[{{ $rowIndex }}][action]"><option value="" @selected(old("players.$rowIndex.action", '') === '')>İşlem seçin</option><option value="map" @selected(old("players.$rowIndex.action") === 'map')>Mevcutla eşleştir</option><option value="create" @selected(old("players.$rowIndex.action") === 'create')>Yeni oluştur</option></select></td>
                                    <td><select class="form-select form-select-sm" name="players[{{ $rowIndex }}][local_player_id]"><option value="">Oyuncu seçin</option>@foreach($preview['manual_candidates'] as $candidate)<option value="{{ $candidate->id }}" @selected((string) old("players.$rowIndex.local_player_id") === (string) $candidate->id)>{{ $candidate->name }}</option>@endforeach</select></td>
                                </tr>
                                @php($rowIndex++)
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($preview['local_not_in_provider_squad']->isNotEmpty())
                <section class="surface mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2"><h2 class="h6 fw-bold mb-0">API kadrosunda görünmeyen yerel oyuncular</h2><span class="badge text-bg-secondary">{{ $preview['local_not_in_provider_squad']->count() }}</span></div>
                    <p class="muted">Bu oyuncular silinmeyecek, pasifleştirilmeyecek ve takım bilgileri değiştirilmeyecek.</p>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Oyuncu</th><th>Provider ID</th><th>Pozisyon</th><th>Forma</th><th>Durum</th></tr></thead><tbody>@foreach($preview['local_not_in_provider_squad'] as $localPlayer)<tr><td><strong>{{ $localPlayer->name }}</strong><small class="d-block muted">/{{ $localPlayer->slug }}</small></td><td>{{ $localPlayer->provider_player_id }}</td><td>{{ $localPlayer->position ?: '—' }}</td><td>{{ $localPlayer->shirt_number !== null ? '#'.$localPlayer->shirt_number : '—' }}</td><td><span class="badge text-bg-secondary">API kadrosunda yok</span></td></tr>@endforeach</tbody></table></div>
                </section>
            @endif

            <div class="d-flex flex-wrap gap-2 justify-content-end mb-4">
                <a class="btn btn-light" href="{{ route('admin.players.import.create') }}">Önizlemeyi iptal et</a>
                <button class="btn btn-primary" type="submit">Seçilenleri İçe Aktar / Senkronize Et</button>
            </div>
        </form>
    @endif
</div>
@endsection
