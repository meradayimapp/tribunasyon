<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Football\LiveFootballApiService;
use App\Services\Football\PlayerSquadSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncPlayerImages extends Command
{
    protected $signature = 'football:sync-player-images {--team= : Yalnız belirtilen yerel takım ID’sini senkronize et}';

    protected $description = 'Provider ID ile eşleşmiş oyuncuların eksik Football API fotoğraflarını takım kadrosundan doldur';

    public function handle(PlayerSquadSyncService $squads): int
    {
        $teamId = $this->option('team');
        if ($teamId !== null && (! ctype_digit((string) $teamId) || (int) $teamId < 1)) {
            $this->error('Takım ID’si pozitif bir sayı olmalıdır.');

            return self::INVALID;
        }

        $teams = Team::query()
            ->active()
            ->whereHas('footballTeams', fn ($query) => $query
                ->where('provider', LiveFootballApiService::PROVIDER)
                ->where('is_active', true)
                ->whereNotNull('provider_team_id'))
            ->whereHas('players', fn ($query) => $query
                ->whereNotNull('provider_player_id')
                ->where('provider_player_id', '!=', '')
                ->where(fn ($images) => $images->whereNull('provider_image_url')->orWhere('provider_image_url', '')))
            ->when($teamId !== null, fn ($query) => $query->whereKey((int) $teamId))
            ->ordered()
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($teams as $team) {
            try {
                $result = $squads->syncMissingPlayerImages($team);
                $updated += $result['updated'];
                $this->line("{$team->name}: {$result['updated']}/{$result['pending']} eksik fotoğraf dolduruldu.");
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->warn("{$team->name}: fotoğraflar senkronize edilemedi; takım eşleştirmesini/API durumunu kontrol edin.");
            }
        }

        $this->info("{$teams->count()} takım kontrol edildi, {$updated} oyuncu fotoğrafı dolduruldu.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
