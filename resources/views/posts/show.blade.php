@extends('layouts.app')
@section('title', $post->team->name.' gönderisi')
@section('mobile-title', 'Gönderi')
@section('mobile-back', route('teams.show', $post->team))
@section('content')
<div class="feed-column public-feed mx-auto">
    <div class="desktop-back"><a href="{{ route('teams.show', $post->team) }}"><x-ui.icon name="back" />{{ $post->team->name }} topluluğuna dön</a></div>
    <x-post-card :post="$post" :show-team="true" />
    <livewire:comments :post="$post" />
</div>
@endsection
