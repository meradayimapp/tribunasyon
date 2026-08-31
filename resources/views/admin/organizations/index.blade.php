@extends('layouts.app')
@section('title', 'Organizasyonlar')
@section('content')
<div class="panel-shell">
    <div class="d-flex justify-content-between align-items-end gap-3">
        <div><div class="eyebrow">Yönetim</div><h1 class="page-title">Organizasyonlar</h1></div>
        <a class="btn btn-primary mb-3" href="{{ route('admin.organizations.create') }}">Yeni organizasyon</a>
    </div>
    <x-panel-nav />

    <div class="surface table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Organizasyon</th><th>Durum</th><th>Takım</th><th></th></tr></thead>
            <tbody>
                @forelse($organizations as $organization)
                    <tr>
                        <td>
                            <div class="admin-organization-identity">
                                @if($organization->logoUrl())<img src="{{ $organization->logoUrl() }}" alt="{{ $organization->name }} logosu">@endif
                                <span><strong>{{ $organization->name }}</strong><small>{{ $organization->slug }}</small></span>
                            </div>
                        </td>
                        <td><span class="status-pill {{ $organization->status->value === 'active' ? 'live' : '' }}">{{ $organization->status->label() }}</span></td>
                        <td>{{ $organization->teams_count }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-light" href="{{ route('admin.organizations.edit', $organization) }}">Düzenle</a>
                            <form class="d-inline" method="POST" action="{{ route('admin.organizations.destroy', $organization) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Organizasyon silinsin mi?')">Sil</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">Henüz organizasyon oluşturulmadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
