<section
    class="player-chat"
    wire:poll.3s.visible="poll"
    x-data="playerChatScroll()"
    @player-chat-prepended.window="afterPrepend()"
    @player-chat-updated.window="afterUpdate()"
    @player-chat-latest.window="afterLatest()"
>
    <div class="player-chat-heading">
        <div><span>Oyuncu sohbeti</span><h2>{{ $player->name }} hakkında konuş</h2></div>
        <small>Herkese açık sohbet</small>
    </div>

    @if($viewingHistory || $unseenCount > 0)
        <button class="chat-new-messages" type="button" wire:click="goLatest">
            <x-ui.icon name="arrow-right" />
            <span>En yeni mesajlara dön{{ $unseenCount > 0 ? ' ('.$unseenCount.')' : '' }}</span>
        </button>
    @endif

    <div class="player-chat-log" x-ref="log" @scroll.passive="onScroll()" role="log" aria-live="polite" aria-label="{{ $player->name }} sohbet mesajları">
        @if($hasOlder)
            <button class="chat-load-older" type="button" @click="beforePrepend(); $wire.loadOlder()" wire:loading.attr="disabled" wire:target="loadOlder">
                <span wire:loading.remove wire:target="loadOlder">Önceki mesajları yükle</span>
                <span wire:loading wire:target="loadOlder"><span class="mini-loader"></span></span>
            </button>
        @endif

        @forelse($messages as $message)
            <article class="player-chat-message" wire:key="player-message-{{ $message['id'] }}">
                <a href="{{ route('profile.show', $message['user']['username']) }}" class="avatar avatar-sm" aria-label="{{ $message['user']['name'] }} profili">
                    @if($message['user']['avatar_url'])<img src="{{ $message['user']['avatar_url'] }}" alt="{{ $message['user']['name'] }} profil fotoğrafı">@else{{ $message['user']['initials'] }}@endif
                </a>
                <div class="player-chat-message-body">
                    <div class="player-chat-message-meta">
                        <a href="{{ route('profile.show', $message['user']['username']) }}">{{ '@'.$message['user']['username'] }}</a>
                        <time datetime="{{ $message['created_at'] }}">{{ $message['time'] }}</time>
                        @if($message['can_delete'])
                            <button type="button" wire:click="deleteMessage({{ $message['id'] }})" wire:confirm="Bu mesaj silinsin mi?" aria-label="Mesajı sil"><x-ui.icon name="delete" /></button>
                        @endif
                    </div>
                    <p>{{ $message['body'] }}</p>
                </div>
            </article>
        @empty
            <div class="chat-empty"><x-ui.icon name="comment" /><strong>İlk mesajı sen yaz.</strong><span>{{ $player->name }} sohbetini başlat.</span></div>
        @endforelse
    </div>

    @auth
        <form wire:submit="send" class="player-chat-composer">
            <label class="visually-hidden" for="player-chat-body-{{ $player->id }}">{{ $player->name }} hakkında yaz</label>
            <textarea id="player-chat-body-{{ $player->id }}" wire:model="body" rows="1" maxlength="500" placeholder="{{ $player->name }} hakkında yaz..." @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
            <button type="submit" wire:loading.attr="disabled" wire:target="send" aria-label="Mesajı gönder"><x-ui.icon name="send" /></button>
        </form>
        @error('body')<div class="chat-error">{{ $message }}</div>@enderror
    @else
        <div class="chat-login-prompt"><span>Sohbete katılmak için giriş yap.</span><a href="{{ route('login') }}">Giriş yap</a></div>
    @endauth
</section>
