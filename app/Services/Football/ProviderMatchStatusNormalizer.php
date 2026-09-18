<?php

namespace App\Services\Football;

class ProviderMatchStatusNormalizer
{
    /**
     * Normalize the provider's match status into the values persisted by the app.
     *
     * @return array{status: string, is_live: bool}
     */
    public function normalize(mixed $status, mixed $state, mixed $isLive, mixed $display = null): array
    {
        $providerStatus = $this->token($status);
        $providerState = $this->token($state);
        $live = filter_var($isLive, FILTER_VALIDATE_BOOLEAN)
            || $providerStatus === 'live'
            || $providerState === 'inplay';

        if ($live) {
            return [
                'status' => $this->isHalfTime($display) ? 'halftime' : 'live',
                'is_live' => true,
            ];
        }

        $normalized = match (true) {
            $providerStatus === 'finished', $providerState === 'postgame' => 'finished',
            $providerStatus === 'postponed', $providerState === 'postponed' => 'postponed',
            in_array($providerStatus, ['cancelled', 'canceled'], true),
            in_array($providerState, ['cancelled', 'canceled'], true) => 'cancelled',
            $providerStatus === 'abandoned', $providerState === 'abandoned' => 'abandoned',
            in_array($providerStatus, ['scheduled', 'notstarted'], true), $providerState === 'pregame' => 'scheduled',
            default => 'unknown',
        };

        return ['status' => $normalized, 'is_live' => false];
    }

    private function isHalfTime(mixed $display): bool
    {
        if (! is_scalar($display)) {
            return false;
        }

        $display = mb_strtoupper(trim((string) $display), 'UTF-8');

        return in_array($display, ['HT', 'İY'], true)
            || str_contains(mb_strtolower($display, 'UTF-8'), 'devre')
            || str_contains(strtolower($display), 'half');
    }

    private function token(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return strtolower((string) preg_replace('/[^a-z]/i', '', (string) $value));
    }
}
