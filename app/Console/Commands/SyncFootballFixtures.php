<?php

namespace App\Console\Commands;

use App\Models\FootballCompetition;
use App\Services\Football\FootballDataSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncFootballFixtures extends Command
{
    protected $signature = 'football:sync-fixtures
        {--competition=* : Exact competition slug or provider league ID}';

    protected $description = 'Aktif futbol organizasyonlarının fikstürlerini senkronize et';

    public function handle(FootballDataSynchronizer $synchronizer): int
    {
        $requestedCompetitions = collect($this->option('competition'))
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->values();
        $competitions = FootballCompetition::query()
            ->active()
            ->when($requestedCompetitions->isNotEmpty(), fn ($query) => $query->where(
                fn ($filter) => $filter
                    ->whereIn('slug', $requestedCompetitions)
                    ->orWhereIn('provider_league_id', $requestedCompetitions)
            ))
            ->ordered()
            ->get();

        if ($requestedCompetitions->isNotEmpty() && $competitions->count() !== $requestedCompetitions->count()) {
            $this->error('İstenen organizasyonlardan en az biri aktif ve kayıtlı değil. Tam slug veya provider league ID kullanın.');

            return self::INVALID;
        }

        if ($competitions->isEmpty()) {
            $this->warn('Senkronize edilecek aktif futbol organizasyonu yok.');

            return self::SUCCESS;
        }

        $failed = false;
        $total = 0;

        foreach ($competitions as $competition) {
            $name = $competition->display_name ?: $competition->name;

            try {
                $count = $synchronizer->syncCompetition($competition);
                $total += $count;
                $this->info("{$name}: {$count} maç senkronize edildi.");
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("{$name}: {$exception->getMessage()}");
            }
        }

        $this->newLine();
        $this->line("Toplam {$total} maç senkronize edildi.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
