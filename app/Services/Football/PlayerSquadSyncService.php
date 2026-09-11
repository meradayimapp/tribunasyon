<?php

namespace App\Services\Football;

use App\Enums\PlayerStatus;
use App\Exceptions\PlayerSquadSyncException;
use App\Models\FootballTeam;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlayerSquadSyncService
{
    private const CACHE_MINUTES = 10;

    public function __construct(private readonly LiveFootballApiService $api) {}

    public function eligibleTeams(): EloquentCollection
    {
        return Team::query()
            ->active()
            ->whereHas('footballTeams', fn ($query) => $query
                ->where('provider', LiveFootballApiService::PROVIDER)
                ->where('is_active', true)
                ->whereNotNull('provider_team_id'))
            ->ordered()
            ->get();
    }

    public function createPreview(User $admin, Team $team): array
    {
        $footballTeam = $this->linkedFootballTeam($team);
        $data = $this->api->teamSquad($footballTeam->provider_team_id);
        $players = $this->normalizeSquad($data['squad']);
        $token = Str::random(64);
        $payload = [
            'admin_id' => $admin->id,
            'team_id' => $team->id,
            'football_team_id' => $footballTeam->id,
            'provider_team_id' => $footballTeam->provider_team_id,
            'season' => $this->nullableString($data['season'] ?? null, 30),
            'players' => $players,
            'expires_at' => now()->addMinutes(self::CACHE_MINUTES)->toIso8601String(),
        ];

        Cache::put($this->cacheKey($admin->id, $team->id, $token), $payload, now()->addMinutes(self::CACHE_MINUTES));

        return $this->presentPreview($payload, $team, $token);
    }

    public function loadPreview(User $admin, Team $team, string $token): array
    {
        $payload = $this->cachedPayload($admin, $team, $token);

        return $this->presentPreview($payload, $team, $token);
    }

    public function apply(User $admin, Team $team, string $token, array $choices): array
    {
        $lock = Cache::lock($this->lockKey($token), 30);

        return $lock->block(2, function () use ($admin, $team, $token, $choices): array {
            $payload = $this->cachedPayload($admin, $team, $token);
            $footballTeam = $this->linkedFootballTeam($team);

            if ($footballTeam->id !== $payload['football_team_id']
                || $footballTeam->provider_team_id !== $payload['provider_team_id']) {
                throw new PlayerSquadSyncException('Takımın Football API eşleştirmesi değişti. Lütfen kadroyu yeniden getirin.');
            }

            $selected = $this->selectedChoices($payload['players'], $choices);

            if ($selected === []) {
                throw new PlayerSquadSyncException('İçe aktarılacak en az bir oyuncu seçin.');
            }

            $summary = DB::transaction(fn (): array => $this->applySelected($team, $selected));

            try {
                Cache::forget($this->cacheKey($admin->id, $team->id, $token));
            } catch (\Throwable $exception) {
                // The database transaction has committed, so a cache cleanup failure must not be reported as a rollback.
                report($exception);
            }

            return $summary;
        });
    }

    private function linkedFootballTeam(Team $team): FootballTeam
    {
        if ($team->trashed() || $team->status->value !== 'active') {
            throw new PlayerSquadSyncException('Bu takım kadro aktarımı için aktif değil.');
        }

        $mappings = FootballTeam::query()
            ->where('team_id', $team->id)
            ->where('provider', LiveFootballApiService::PROVIDER)
            ->where('is_active', true)
            ->whereNotNull('provider_team_id')
            ->get();

        if ($mappings->isEmpty()) {
            throw new PlayerSquadSyncException('Bu takım Football API ile eşleştirilmemiş.');
        }

        if ($mappings->count() !== 1) {
            throw new PlayerSquadSyncException('Bu takımın Football API eşleştirmesi belirsiz. Lütfen takım eşleştirmesini kontrol edin.');
        }

        return $mappings->first();
    }

    private function normalizeSquad(array $squad): array
    {
        $normalized = [];
        $seen = [];

        foreach ($squad as $item) {
            if (! is_array($item)) {
                throw new PlayerSquadSyncException('Football API kadrosunda geçersiz bir oyuncu kaydı bulundu.');
            }

            $providerPlayerId = $this->requiredString($item['id'] ?? null, 100, 'Football API kadrosunda oyuncu kimliği eksik veya geçersiz.');
            $name = $this->requiredString($item['name'] ?? null, 120, 'Football API kadrosunda oyuncu adı eksik veya geçersiz.');

            if (isset($seen[$providerPlayerId])) {
                throw new PlayerSquadSyncException('Football API kadrosunda aynı oyuncu kimliği birden fazla kez döndü.');
            }

            $seen[$providerPlayerId] = true;
            $position = $this->localizedPosition($item['position'] ?? null);
            $normalized[] = [
                'provider_player_id' => $providerPlayerId,
                'name' => $name,
                'position' => $position['value'],
                'position_requires_manual_edit' => $position['requires_manual_edit'],
                'shirt_number' => $this->shirtNumber($item['number'] ?? null),
                'nationality' => $this->nullableString($item['country'] ?? null, 100),
                'age' => $this->age($item['age'] ?? null),
            ];
        }

        if ($normalized === []) {
            throw new PlayerSquadSyncException('Bu takım için Football API kadrosu bulunamadı.');
        }

        return $normalized;
    }

    private function presentPreview(array $payload, Team $team, string $token): array
    {
        $providerIds = array_column($payload['players'], 'provider_player_id');
        $mapped = Player::withTrashed()
            ->whereIn('provider_player_id', $providerIds)
            ->get()
            ->keyBy('provider_player_id');
        $candidates = Player::query()
            ->where('current_team_id', $team->id)
            ->whereNull('provider_player_id')
            ->ordered()
            ->get(['id', 'name', 'slug']);
        $candidateGroups = $candidates->groupBy(fn (Player $player): string => $this->comparableName($player->name));
        $groups = [
            'existing_mapped' => [],
            'new' => [],
            'needs_manual_mapping' => [],
        ];

        foreach ($payload['players'] as $player) {
            $existing = $mapped->get($player['provider_player_id']);

            if ($existing) {
                $groups['existing_mapped'][] = $player + [
                    'local_player_id' => $existing->id,
                    'local_player_name' => $existing->name,
                    'local_player_slug' => $existing->slug,
                    'local_player_deleted' => $existing->trashed(),
                ];

                continue;
            }

            $suggestions = $candidateGroups->get($this->comparableName($player['name']), collect());
            if ($suggestions->isNotEmpty()) {
                $groups['needs_manual_mapping'][] = $player + [
                    'suggestions' => $suggestions->map->only(['id', 'name', 'slug'])->values()->all(),
                ];
            } else {
                $groups['new'][] = $player;
            }
        }

        $localNotInSquad = Player::query()
            ->where('current_team_id', $team->id)
            ->whereNotNull('provider_player_id')
            ->whereNotIn('provider_player_id', $providerIds)
            ->ordered()
            ->get(['id', 'name', 'slug', 'provider_player_id', 'position', 'shirt_number']);

        return [
            'token' => $token,
            'team' => $team,
            'season' => $payload['season'],
            'expires_at' => $payload['expires_at'],
            'total' => count($payload['players']),
            'groups' => $groups,
            'manual_candidates' => $candidates,
            'local_not_in_provider_squad' => $localNotInSquad,
        ];
    }

    private function cachedPayload(User $admin, Team $team, string $token): array
    {
        if (! preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            throw new PlayerSquadSyncException('Kadro önizlemesi geçersiz veya süresi dolmuş. Lütfen kadroyu yeniden getirin.');
        }

        $payload = Cache::get($this->cacheKey($admin->id, $team->id, $token));

        if (! is_array($payload)
            || ($payload['admin_id'] ?? null) !== $admin->id
            || ($payload['team_id'] ?? null) !== $team->id
            || ! is_array($payload['players'] ?? null)) {
            throw new PlayerSquadSyncException('Kadro önizlemesi geçersiz veya süresi dolmuş. Lütfen kadroyu yeniden getirin.');
        }

        return $payload;
    }

    private function selectedChoices(array $cachedPlayers, array $choices): array
    {
        $playersById = collect($cachedPlayers)->keyBy('provider_player_id');
        $selected = [];
        $seenProviderIds = [];
        $mappedLocalIds = [];

        foreach ($choices as $choice) {
            if (! is_array($choice)) {
                throw new PlayerSquadSyncException('Oyuncu seçimleri geçersiz.');
            }

            $providerPlayerId = is_scalar($choice['provider_player_id'] ?? null)
                ? trim((string) $choice['provider_player_id'])
                : '';

            if ($providerPlayerId === '' || isset($seenProviderIds[$providerPlayerId])) {
                throw new PlayerSquadSyncException('Oyuncu seçimleri geçersiz veya tekrarlı.');
            }
            $seenProviderIds[$providerPlayerId] = true;

            if (! filter_var($choice['selected'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $player = $playersById->get($providerPlayerId);
            if (! is_array($player)) {
                throw new PlayerSquadSyncException('Seçilen oyuncu bu kadro önizlemesinde bulunmuyor.');
            }

            $action = is_scalar($choice['action'] ?? null) ? (string) $choice['action'] : '';
            if (! in_array($action, ['sync', 'create', 'map'], true)) {
                throw new PlayerSquadSyncException("{$player['name']} için yapılacak işlemi seçin.");
            }

            $localPlayerId = null;
            if ($action === 'map') {
                $localPlayerId = filter_var($choice['local_player_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($localPlayerId === false || isset($mappedLocalIds[$localPlayerId])) {
                    throw new PlayerSquadSyncException('Manuel eşleştirmede her yerel oyuncu yalnız bir kez seçilebilir.');
                }
                $mappedLocalIds[$localPlayerId] = true;
            }

            $selected[] = [
                'player' => $player,
                'action' => $action,
                'local_player_id' => $localPlayerId,
            ];
        }

        return $selected;
    }

    private function applySelected(Team $team, array $selected): array
    {
        $summary = ['processed' => 0, 'created' => 0, 'updated' => 0, 'mapped' => 0, 'errors' => 0];
        $syncedAt = CarbonImmutable::now('UTC');

        foreach ($selected as $selection) {
            $apiPlayer = $selection['player'];
            $existing = Player::withTrashed()
                ->where('provider_player_id', $apiPlayer['provider_player_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($selection['action'] === 'map' && $selection['local_player_id'] !== $existing->id) {
                    throw new PlayerSquadSyncException("{$apiPlayer['name']} başka bir yerel oyuncuya zaten bağlı.");
                }

                $existing->update($this->apiControlledAttributes($apiPlayer, $team, $syncedAt));
                $summary['updated']++;
            } elseif ($selection['action'] === 'map') {
                $localPlayer = Player::query()
                    ->whereKey($selection['local_player_id'])
                    ->where('current_team_id', $team->id)
                    ->whereNull('provider_player_id')
                    ->lockForUpdate()
                    ->first();

                if (! $localPlayer) {
                    throw new PlayerSquadSyncException("{$apiPlayer['name']} için seçilen yerel oyuncu artık eşleştirilemez.");
                }

                $localPlayer->update($this->apiControlledAttributes($apiPlayer, $team, $syncedAt) + [
                    'provider_player_id' => $apiPlayer['provider_player_id'],
                ]);
                $summary['mapped']++;
            } elseif ($selection['action'] === 'create') {
                Player::create($this->apiControlledAttributes($apiPlayer, $team, $syncedAt) + [
                    'provider_player_id' => $apiPlayer['provider_player_id'],
                    'slug' => $this->uniqueSlug($apiPlayer['name']),
                    'status' => PlayerStatus::Active,
                    'sort_order' => 0,
                ]);
                $summary['created']++;
            } else {
                throw new PlayerSquadSyncException("{$apiPlayer['name']} için senkronize edilecek yerel oyuncu bulunamadı.");
            }

            $summary['processed']++;
        }

        return $summary;
    }

    private function apiControlledAttributes(array $apiPlayer, Team $team, CarbonImmutable $syncedAt): array
    {
        $attributes = [
            'name' => $apiPlayer['name'],
            'current_team_id' => $team->id,
            'provider_last_synced_at' => $syncedAt,
        ];

        foreach (['position', 'shirt_number', 'nationality'] as $field) {
            if ($apiPlayer[$field] !== null) {
                $attributes[$field] = $apiPlayer[$field];
            }
        }

        return $attributes;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'oyuncu';
        $base = mb_substr($base, 0, 140);
        $slug = $base;
        $suffix = 2;

        while (Player::withTrashed()->where('slug', $slug)->exists()) {
            $ending = '-'.$suffix++;
            $slug = mb_substr($base, 0, 140 - strlen($ending)).$ending;
        }

        return $slug;
    }

    private function comparableName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)), 'UTF-8');
    }

    private function requiredString(mixed $value, int $maxLength, string $message): string
    {
        if (! is_scalar($value)) {
            throw new PlayerSquadSyncException($message);
        }

        $value = trim(strip_tags((string) $value));

        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new PlayerSquadSyncException($message);
        }

        return $value;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    /**
     * @return array{value: ?string, requires_manual_edit: bool}
     */
    private function localizedPosition(mixed $value): array
    {
        $position = $this->nullableString($value, 80);

        if ($position === null) {
            return ['value' => null, 'requires_manual_edit' => false];
        }

        $localized = match (mb_strtolower($position, 'UTF-8')) {
            'goalkeeper', 'keeper', 'kaleci' => 'Kaleci',
            'defender', 'defence', 'defense', 'defans' => 'Defans',
            'midfielder', 'midfield', 'orta saha' => 'Orta saha',
            'attacker', 'forward', 'forvet' => null,
            default => null,
        };

        return [
            'value' => $localized,
            'requires_manual_edit' => $localized === null,
        ];
    }

    private function shirtNumber(mixed $value): ?int
    {
        if ((is_int($value) || (is_string($value) && preg_match('/^\d{1,2}$/', trim($value))))
            && (int) $value >= 0 && (int) $value <= 99) {
            return (int) $value;
        }

        return null;
    }

    private function age(mixed $value): ?int
    {
        if ((is_int($value) || (is_string($value) && preg_match('/^\d{1,3}$/', trim($value))))
            && (int) $value >= 0 && (int) $value <= 120) {
            return (int) $value;
        }

        return null;
    }

    private function cacheKey(int $adminId, int $teamId, string $token): string
    {
        return 'football:player-squad-preview:'.hash('sha256', "{$adminId}:{$teamId}:{$token}");
    }

    private function lockKey(string $token): string
    {
        return 'football:player-squad-apply:'.hash('sha256', $token);
    }
}
