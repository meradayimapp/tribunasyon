<?php

namespace App\Console\Commands;

use App\Models\FootballCompetition;
use App\Services\Football\FootballDataSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncFootballFixtures extends Command
{
    protected $signature = 'football:sync-fixtures';

    protected $description = 'Aktif futbol organizasyonlarının fikstürlerini senkronize et';

    public function handle(FootballDataSynchronizer $synchronizer): int
    {
        $competitions = FootballCompetition::query()->active()->ordered()->get();

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
