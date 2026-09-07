<?php

namespace App\Livewire;

use App\Enums\PlayerStatus;
use App\Models\Player;
use App\Models\PlayerChatMessage;
use App\Services\PlayerChatQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class PlayerChat extends Component
{
    private const WINDOW_SIZE = 250;

    public Player $player;

    public array $messages = [];

    public string $body = '';

    public ?int $oldestVisibleId = null;

    public ?int $newestVisibleId = null;

    public int $lastKnownMessageId = 0;

    public bool $hasOlder = false;

    public bool $viewingHistory = false;

    public int $unseenCount = 0;

    public bool $canModerate = false;

    public function mount(PlayerChatQuery $query): void
    {
        abort_unless($this->player->status === PlayerStatus::Active && ! $this->player->trashed(), 404);
        $this->canModerate = auth()->check() && (
            auth()->user()->isAdmin()
            || (auth()->user()->isModerator()
                && $this->player->current_team_id !== null
                && auth()->user()->moderatedTeams()->whereKey($this->player->current_team_id)->exists())
        );
        $this->replaceWithLatest($query);
    }

    public function loadOlder(PlayerChatQuery $query): void
    {
        if (! $this->hasOlder || $this->oldestVisibleId === null) {
            return;
        }

        $batch = $query->before($this->player, $this->oldestVisibleId);
        $older = $this->serializeMessages($batch['messages']);
        $this->messages = $this->deduplicate([...$older, ...$this->messages]);
        $this->hasOlder = $batch['has_older'];

        if (count($this->messages) > self::WINDOW_SIZE) {
            $this->messages = array_slice($this->messages, 0, self::WINDOW_SIZE);
            $this->viewingHistory = true;
        }

        $this->recalculateBounds();
        $this->dispatch('player-chat-prepended');
    }

    public function poll(PlayerChatQuery $query): void
    {
        if ($this->viewingHistory) {
            $delta = $query->delta($this->player, $this->lastKnownMessageId);
            $this->unseenCount += $delta['count'];
            $this->lastKnownMessageId = max($this->lastKnownMessageId, $delta['max_id']);

            return;
        }

        $new = $query->after($this->player, $this->lastKnownMessageId);
        if ($new->isEmpty()) {
            return;
        }

        $this->messages = $this->deduplicate([...$this->messages, ...$this->serializeMessages($new)]);
        $this->lastKnownMessageId = max($this->lastKnownMessageId, (int) $new->max('id'));

        if (count($this->messages) > self::WINDOW_SIZE) {
            $this->messages = array_slice($this->messages, -self::WINDOW_SIZE);
            $this->hasOlder = true;
        }

        $this->recalculateBounds();
        $this->dispatch('player-chat-updated');
    }

    public function goLatest(PlayerChatQuery $query): void
    {
        $this->replaceWithLatest($query);
        $this->dispatch('player-chat-latest');
    }

    public function send(PlayerChatQuery $query): mixed
    {
        if (! auth()->check()) {
            return $this->redirectRoute('login', navigate: true);
        }

        abort_unless(auth()->user()->isActive(), 403);
        Gate::authorize('sendMessage', $this->player);

        $this->body = trim($this->body);
        $this->validate(['body' => ['required', 'string', 'max:500']]);

        $userId = (int) auth()->id();
        $burstKey = "player-chat:burst:{$userId}";
        $minuteKey = "player-chat:minute:{$userId}";

        if (RateLimiter::tooManyAttempts($burstKey, 1) || RateLimiter::tooManyAttempts($minuteKey, 10)) {
            $this->addError('body', 'Çok hızlı mesaj gönderiyorsun. Lütfen kısa bir süre bekle.');

            return null;
        }

        RateLimiter::hit($burstKey, 3);
        RateLimiter::hit($minuteKey, 60);
        $message = $this->player->chatMessages()->create(['user_id' => $userId, 'body' => $this->body]);
        $message->load('user:id,name,username,avatar_path');
        $this->reset('body');

        if ($this->viewingHistory) {
            $this->replaceWithLatest($query);
        } else {
            $this->messages = $this->deduplicate([...$this->messages, ...$this->serializeMessages(new Collection([$message]))]);
            if (count($this->messages) > self::WINDOW_SIZE) {
                $this->messages = array_slice($this->messages, -self::WINDOW_SIZE);
                $this->hasOlder = true;
            }
            $this->lastKnownMessageId = max($this->lastKnownMessageId, $message->id);
            $this->recalculateBounds();
        }

        $this->dispatch('player-chat-updated');

        return null;
    }

    public function deleteMessage(int $messageId): void
    {
        $message = $this->player->chatMessages()->findOrFail($messageId);
        Gate::authorize('delete', $message);
        $message->delete();
        $this->messages = array_values(array_filter($this->messages, fn (array $item): bool => $item['id'] !== $messageId));
        $this->recalculateBounds();
    }

    public function render(): View
    {
        return view('livewire.player-chat');
    }

    private function replaceWithLatest(PlayerChatQuery $query): void
    {
        $batch = $query->latest($this->player);
        $this->messages = $this->serializeMessages($batch['messages']);
        $this->hasOlder = $batch['has_older'];
        $this->viewingHistory = false;
        $this->unseenCount = 0;
        $this->recalculateBounds();
        $this->lastKnownMessageId = max($this->lastKnownMessageId, $this->newestVisibleId ?? 0);
    }

    private function serializeMessages(Collection $messages): array
    {
        $viewerId = auth()->id();

        return $messages->map(function (PlayerChatMessage $message) use ($viewerId): array {
            return [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
                'time' => $message->created_at->format('H:i'),
                'can_delete' => $this->canModerate || $viewerId === $message->user_id,
                'user' => [
                    'name' => $message->user->name,
                    'username' => $message->user->username,
                    'avatar_url' => $message->user->avatar_path
                        ? Storage::disk('public')->url($message->user->avatar_path)
                        : null,
                    'initials' => mb_strtoupper(mb_substr($message->user->name, 0, 2)),
                ],
            ];
        })->all();
    }

    private function deduplicate(array $messages): array
    {
        $unique = [];
        foreach ($messages as $message) {
            $unique[$message['id']] = $message;
        }
        ksort($unique, SORT_NUMERIC);

        return array_values($unique);
    }

    private function recalculateBounds(): void
    {
        $this->oldestVisibleId = $this->messages[0]['id'] ?? null;
        $this->newestVisibleId = $this->messages[array_key_last($this->messages)]['id'] ?? null;
    }
}
