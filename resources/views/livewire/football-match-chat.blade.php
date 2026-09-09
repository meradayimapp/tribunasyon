<section
    class="player-chat match-chat"
    wire:poll.3s.visible="poll"
    x-data="playerChatScroll()"
    @football-match-chat-updated.window="afterUpdate()"
    @football-match-chat-latest.window="afterLatest()"
>
    <div class="player-chat-heading">
        <div class="player-chat-title">
            <span class="match-chat-community-icon"><x-ui.icon name="comment" /></span>
            <div>
                <h2>{{ $footballMatch->is_live ? 'Canlı Maç Sohbeti' : 'Maç Sohbeti' }}</h2>
                <p>{{ strtolower($footballMatch->status) === 'finished' ? 'Maç bitti, sohbet devam ediyor' : 'Tribünle birlikte takip et' }}</p>
            </div>
        </div>
        <span class="player-chat-refresh"><x-ui.icon name="activity" /> 3 sn'de güncellenir</span>
    </div>

    @if($viewingHistory || $unseenCount > 0)
        <button class="chat-new-messages" type="button" wire:click="goLatest">
            <x-ui.icon name="activity" />
            <span>{{ $unseenCount > 0 ? $unseenCount.' yeni mesaj' : 'Yeni mesajlar' }} · En üste dön</span>
        </button>
    @endif

    <div class="player-chat-log" x-ref="log" @scroll.passive="onScroll()" role="log" aria-live="polite" aria-label="Maç sohbeti mesajları">
        @forelse($messages as $message)
            <article class="player-chat-message role-{{ $message['user']['role'] }}" wire:key="football-match-message-{{ $message['id'] }}">
                <a href="{{ route('profile.show', $message['user']['username']) }}" class="avatar avatar-sm" aria-label="{{ $message['user']['name'] }} profili">
                    @if($message['user']['avatar_url'])<img src="{{ $message['user']['avatar_url'] }}" alt="{{ $message['user']['name'] }} profil fotoğrafı">@else{{ $message['user']['initials'] }}@endif
                </a>
                <div class="player-chat-message-body">
                    <div class="player-chat-message-meta">
                        <a href="{{ route('profile.show', $message['user']['username']) }}">{{ '@'.$message['user']['username'] }}</a>
                        @if($message['user']['role_label'])
                            <span class="chat-role-badge"><x-ui.icon :name="$message['user']['role'] === 'admin' ? 'check-circle' : 'sparkle'" />{{ $message['user']['role_label'] }}</span>
                        @endif
                        <time datetime="{{ $message['created_at'] }}">{{ $message['time'] }}</time>
                        @if($message['can_delete'])<button type="button" wire:click="deleteMessage({{ $message['id'] }})" wire:confirm="Bu mesaj silinsin mi?" aria-label="Mesajı sil"><x-ui.icon name="delete" /></button>@endif
                    </div>
                    <p>{{ $message['body'] }}</p>
                </div>
            </article>
        @empty
            <div class="chat-empty"><span class="chat-empty-icon"><x-ui.icon name="comment" /></span><strong>Maç sohbetini sen başlat.</strong><span>Bu maç hakkındaki ilk mesajı yaz.</span></div>
        @endforelse

        @if($hasOlder)
            <button class="chat-load-older" type="button" wire:click="loadOlder" wire:loading.attr="disabled" wire:target="loadOlder">
                <span wire:loading.remove wire:target="loadOlder">Daha eski 50 mesajı göster</span>
                <span wire:loading wire:target="loadOlder"><span class="mini-loader"></span> Yükleniyor</span>
            </button>
        @endif
    </div>

    @auth
        <div class="player-chat-composer-shell" x-data="{ length: 0 }" @football-match-chat-sent.window="length = 0">
            <form wire:submit="send" class="player-chat-composer">
                <x-avatar :user="auth()->user()" size="sm" />
                <div class="player-chat-input-wrap">
                    <label class="visually-hidden" for="football-match-chat-body-{{ $footballMatch->id }}">Maç hakkında yaz</label>
                    <textarea id="football-match-chat-body-{{ $footballMatch->id }}" wire:model="body" rows="1" maxlength="500" placeholder="Maç sohbetine bir mesaj yaz..." @input="length = $event.target.value.length; $event.target.style.height = 'auto'; $event.target.style.height = Math.min($event.target.scrollHeight, 112) + 'px'" @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
                    <div class="player-chat-input-meta"><span>Enter gönderir · Shift+Enter yeni satır</span><span x-text="length + '/500'">0/500</span></div>
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="send" aria-label="Mesajı gönder"><span wire:loading.remove wire:target="send"><x-ui.icon name="send" /></span><span wire:loading wire:target="send" class="mini-loader"></span></button>
            </form>
            @error('body')<div class="chat-error">{{ $message }}</div>@enderror
        </div>
    @else
        <div class="chat-login-prompt"><span><strong>Maç sohbetine katıl.</strong> Mesaj yazmak için hesabına giriş yap.</span><a href="{{ route('login') }}">Giriş yap</a></div>
    @endauth
</section>
