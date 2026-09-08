<?php

namespace App\Console\Commands;

use App\Services\Football\FootballDataSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SyncDailyFootballMatches extends Command
{
    protected $signature = 'football:sync-daily {date? : YYYY-MM-DD biçiminde maç tarihi}';

    protected $description = 'Aktif organizasyonların günlük maçlarını senkronize et';

    public function handle(FootballDataSynchronizer $synchronizer): int
    {
        $date = $this->argument('date') ?: Carbon::today('Europe/Istanbul')->format('Y-m-d');

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Tarih YYYY-MM-DD biçiminde olmalıdır.');

            return self::INVALID;
        }

        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date, 'Europe/Istanbul');
        } catch (Throwable) {
            $parsed = null;
        }

        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            $this->error('Geçerli bir tarih girin.');

            return self::INVALID;
        }

        try {
            $result = $synchronizer->syncDate($date);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$date}: {$result['synced']} maç senkronize edildi.");
        $this->line("Takip edilmeyen organizasyonlara ait {$result['ignored']} maç atlandı.");

        return self::SUCCESS;
    }
}
