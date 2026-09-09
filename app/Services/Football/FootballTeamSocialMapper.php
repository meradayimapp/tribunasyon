<?php

namespace App\Services\Football;

use App\Models\FootballTeam;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class FootballTeamSocialMapper
{
    public const MAPPINGS = [
        'esa748l653sss1wurz5ps3228' => 'galatasaray',
        '8lroq0cbhdxj8124qtxwrhvmm' => 'fenerbahce',
        '2ez9cvam9lp9jyhng3eh3znb4' => 'besiktas',
        '2yab38jdfl0gk2tei1mq40o06' => 'trabzonspor',
        '47njg6cmlx5q3fvdsupd2n6qu' => 'basaksehir',
        'cw4lbdzlqqdvbkdkz00c9ye49' => 'konyaspor',
        '1lbrlj3uu8wi2h9j79snuoae4' => 'rizespor',
        '2agzb2h4ppg7lfz9hn7eg1rqo' => 'gaziantep-fk',
        '84fpe0iynjdghwysyo5tizdkk' => 'alanyaspor',
        'embqktr41hfzczc8uav1scmcn' => 'genclerbirligi',
        '4idg23egrrvtrbgrg7p5x7bwf' => 'kasimpasa',
        'dpsnqu7pd2b0shfzjyn5j1znf' => 'samsunspor',
        'cjbaf8s09qoa1n11r33gc560x' => 'goztepe',
        'bmgtxgipsznlb1j20zwjti3xh' => 'eyupspor',
        'b703zecenioz21dnj3p63v3f7' => 'kocaelispor',
        '2154uhyeun0lm781iiiijqhwo' => 'amed',
        'ea2gyhkv6vwmxbxevdb4u3796' => 'erzurumspor',
        'eg0cqg1u8zz85ma9nzk0cijv' => 'corum-fk',
    ];

    private const LABELS = [
        'esa748l653sss1wurz5ps3228' => 'Galatasaray',
        '8lroq0cbhdxj8124qtxwrhvmm' => 'Fenerbahçe',
        '2ez9cvam9lp9jyhng3eh3znb4' => 'Beşiktaş',
        '2yab38jdfl0gk2tei1mq40o06' => 'Trabzonspor',
        '47njg6cmlx5q3fvdsupd2n6qu' => 'Başakşehir',
        'cw4lbdzlqqdvbkdkz00c9ye49' => 'Konyaspor',
        '1lbrlj3uu8wi2h9j79snuoae4' => 'Çaykur Rizespor',
        '2agzb2h4ppg7lfz9hn7eg1rqo' => 'Gaziantep FK',
        '84fpe0iynjdghwysyo5tizdkk' => 'Alanyaspor',
        'embqktr41hfzczc8uav1scmcn' => 'Gençlerbirliği',
        '4idg23egrrvtrbgrg7p5x7bwf' => 'Kasımpaşa',
        'dpsnqu7pd2b0shfzjyn5j1znf' => 'Samsunspor',
        'cjbaf8s09qoa1n11r33gc560x' => 'Göztepe',
        'bmgtxgipsznlb1j20zwjti3xh' => 'Eyüpspor',
        'b703zecenioz21dnj3p63v3f7' => 'Kocaelispor',
        '2154uhyeun0lm781iiiijqhwo' => 'Amed',
        'ea2gyhkv6vwmxbxevdb4u3796' => 'Erzurumspor',
        'eg0cqg1u8zz85ma9nzk0cijv' => 'Çorum FK',
    ];

    public function socialTeamId(string $providerTeamId): ?int
    {
        $providerTeamId = $this->normalizedProviderTeamId($providerTeamId);
        $slug = self::MAPPINGS[$providerTeamId] ?? null;

        return $slug === null
            ? null
            : Team::query()->where('slug', $slug)->value('id');
    }

    public function linkExistingTeams(): array
    {
        $socialTeams = Team::query()
            ->whereIn('slug', array_values(self::MAPPINGS))
            ->get(['id', 'name', 'slug']);
        $footballTeams = FootballTeam::query()
            ->with('team:id,name')
            ->where('provider', LiveFootballApiService::PROVIDER)
            ->whereIn('provider_team_id', array_keys(self::MAPPINGS))
            ->get()
            ->keyBy('provider_team_id');
        $results = [];

        DB::transaction(function () use ($socialTeams, $footballTeams, &$results): void {
            foreach (self::MAPPINGS as $providerTeamId => $slug) {
                $label = self::LABELS[$providerTeamId];
                $socialTeam = $socialTeams->firstWhere('slug', $slug);
                $footballTeam = $footballTeams->get($providerTeamId);

                if ($socialTeam === null) {
                    $results[] = $this->result($label, $providerTeamId, 'missing_social');

                    continue;
                }

                if ($footballTeam === null) {
                    $results[] = $this->result($label, $providerTeamId, 'missing_football');

                    continue;
                }

                if ($footballTeam->team_id === $socialTeam->id) {
                    $results[] = $this->result($label, $providerTeamId, 'already_linked');

                    continue;
                }

                $previousTeamId = $footballTeam->team_id;
                $previousTeam = $footballTeam->team?->name;
                $footballTeam->update(['team_id' => $socialTeam->id]);
                $results[] = $this->result(
                    $label,
                    $providerTeamId,
                    $previousTeamId === null ? 'linked' : 'corrected',
                    $previousTeam,
                );
            }
        });

        return $results;
    }

    private function normalizedProviderTeamId(string $providerTeamId): string
    {
        return str_starts_with($providerTeamId, 'lfa-') ? substr($providerTeamId, 4) : $providerTeamId;
    }

    private function result(string $team, string $providerTeamId, string $status, ?string $previousTeam = null): array
    {
        return compact('team', 'providerTeamId', 'status', 'previousTeam');
    }
}
