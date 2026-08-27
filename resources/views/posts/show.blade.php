@extends('layouts.app')
@section('title', $post->team->name.' gönderisi')
@section('content')
<div class="feed-column mx-auto">
    <div class="page-head mb-3"><a class="small fw-bold text-primary" href="{{ route('teams.show', $post->team) }}"><i class="bi bi-arrow-left"></i> {{ $post->team->name }} topluluğuna dön</a></div>
    <x-post-card :post="$post" :show-team="true" />
    <div class="surface mobile-edge"><livewire:comments :post="$post" /></div>
</div>
@endsection
