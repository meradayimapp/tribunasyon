@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('content')
<div class="panel-shell"><div class="eyebrow">Yönetim</div><h1 class="page-title">Sistem özeti</h1><x-panel-nav />
<div class="stat-grid">@foreach($stats as $label=>$value)<div class="stat"><strong>{{ number_format($value,0,',','.') }}</strong><span class="muted">{{ ['teams'=>'Takım','members'=>'Üye','moderators'=>'Moderatör','posts'=>'Gönderi','comments'=>'Yorum'][$label] }}</span></div>@endforeach</div>
<div class="surface mt-4"><h2 class="h5 fw-bold">Hızlı işlemler</h2><div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-primary" href="{{ route('admin.teams.create') }}">Takım oluştur</a><a class="btn btn-outline-dark" href="{{ route('admin.users.index') }}">Kullanıcı yönetimi</a><a class="btn btn-outline-dark" href="{{ route('admin.posts.index') }}">İçerikleri incele</a></div></div></div>
@endsection
