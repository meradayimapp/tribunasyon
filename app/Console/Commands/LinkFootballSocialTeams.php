<?php

namespace App\Console\Commands;

use App\Services\Football\FootballTeamSocialMapper;
use Illuminate\Console\Command;

class LinkFootballSocialTeams extends Command
{
    protected $signature = 'football:link-social-teams';

    protected $description = 'Explicit provider kimlikleriyle mevcut futbol takımlarını sosyal takım profillerine bağla';

    public function handle(FootballTeamSocialMapper $mapper): int
    {
        $results = $mapper->linkExistingTeams();
        $labels = [
            'linked' => 'linked',
            'already_linked' => 'already linked',
            'corrected' => 'corrected',
            'missing_social' => 'missing social team',
            'missing_football' => 'missing football team',
        ];

        $this->table(
            ['Sosyal takım', 'Provider team ID', 'Durum'],
            collect($results)->map(fn (array $result): array => [
                $result['team'],
                $result['providerTeamId'],
                $labels[$result['status']].($result['previousTeam'] ? " ({$result['previousTeam']} → {$result['team']})" : ''),
            ])->all(),
        );

        $counts = collect($results)->countBy('status');
        $linkedTotal = $counts->get('linked', 0) + $counts->get('already_linked', 0) + $counts->get('corrected', 0);

        $this->newLine();
        $this->line("Successfully linked: {$linkedTotal}");
        $this->line("Linked: {$counts->get('linked', 0)}");
        $this->line("Already linked: {$counts->get('already_linked', 0)}");
        $this->line("Corrected: {$counts->get('corrected', 0)}");
        $this->line("Missing social team: {$counts->get('missing_social', 0)}");
        $this->line("Missing football team: {$counts->get('missing_football', 0)}");

        return self::SUCCESS;
    }
}
