<?php

namespace App\Console\Commands;

use App\Services\Football\FootballLiveSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncLiveFootballMatches extends Command
{
    protected $signature = 'football:sync-live';

    protected $description = 'Başlamak üzere olan ve canlı futbol maçlarını merkezi olarak senkronize et';

    public function handle(FootballLiveSynchronizer $synchronizer): int
    {
        $lock = Cache::lock('football:sync-live:lock', 55);

        if (! $lock->get()) {
            $this->line('Başka bir canlı maç senkronizasyonu devam ediyor.');

            return self::SUCCESS;
        }

        try {
            $result = $synchronizer->sync();
        } catch (Throwable) {
            $this->error('Canlı maç senkronizasyonu tamamlanamadı. Mevcut veriler korundu.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        $this->info("{$result['candidates']} aday değerlendirildi; {$result['details']} canlı detay güncellendi.");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} API isteği başarısız oldu; mevcut veriler korundu.");
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
