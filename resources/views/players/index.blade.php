@extends('layouts.app')
@section('title', 'Oyuncular')
@section('mobile-title', 'Oyuncular')
@section('mobile-back', route('home'))
@section('content')
<div class="players-page mx-auto">
    <div class="page-head mobile-pad">
        <div class="eyebrow">Futbolun konuşulan isimleri</div>
        <h1 class="page-title">Oyuncuları keşfet</h1>
        <p class="muted mb-0">Takip et, profillerini incele ve canlı sohbete katıl.</p>
    </div>
    <livewire:player-directory />
</div>
@endsection
