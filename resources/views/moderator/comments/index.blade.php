@extends('layouts.app')
@section('title', 'Yorum yönetimi')
@section('content')
<div class="panel-shell"><div class="eyebrow">Moderatör paneli</div><h1 class="page-title">Yorum yönetimi</h1><x-panel-nav type="moderator" /><div class="surface table-responsive"><table class="table mb-0"><thead><tr><th>Yorum</th><th>Kullanıcı</th><th>Takım</th><th></th></tr></thead><tbody>@forelse($comments as $comment)<tr><td style="max-width:480px">{{ Str::limit($comment->body,130) }}</td><td>{{ '@'.$comment->user->username }}</td><td>{{ $comment->post->team->name }}</td><td><form method="POST" action="{{ route('moderator.comments.destroy',$comment) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Kaldır</button></form></td></tr>@empty<tr><td colspan="4">Yorum bulunmuyor.</td></tr>@endforelse</tbody></table></div><div class="mt-3">{{ $comments->links() }}</div></div>
@endsection
