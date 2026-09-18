<?php

namespace App\Console\Commands;

use App\Services\Football\FootballLiveSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
            Log::notice('Canlı maç senkronizasyonu uygulama kilidi nedeniyle atlandı.');

            return self::SUCCESS;
        }

        try {
            $result = $synchronizer->sync();
        } catch (Throwable $exception) {
            Log::error('Canlı maç senkronizasyonu tamamlanamadı.', [
                'exception' => $exception::class,
            ]);
            $this->error('Canlı maç senkronizasyonu tamamlanamadı. Mevcut veriler korundu.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        $this->info("{$result['candidates']} aday değerlendirildi; {$result['details']} canlı detay, {$result['lineups']} ilk 11 güncellendi.");

        if ($this->output->isVerbose()) {
            foreach ($result['candidate_matches'] as $candidate) {
                $this->line(sprintf(
                    'Aday: local=%s provider=%s kickoff=%s status=%s live=%s',
                    $candidate['id'],
                    $candidate['provider_match_id'],
                    $candidate['kickoff_at'],
                    $candidate['status'],
                    $candidate['is_live'] ? 'yes' : 'no',
                ));
            }

            foreach ($result['failures'] as $failure) {
                $this->warn(sprintf(
                    'Hata: operation=%s category=%s http=%s local=%s provider=%s date=%s',
                    $failure['operation'],
                    $failure['category'],
                    $failure['http_status'] ?? '-',
                    $failure['match_id'] ?? '-',
                    $failure['provider_match_id'] ?? '-',
                    $failure['date'] ?? '-',
                ));
            }
        }

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} API isteği başarısız oldu; mevcut veriler korundu.");
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
