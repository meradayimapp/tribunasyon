@forelse($shownTables as $table)
    @php
        $titleParts = filled($table['title']) ? array_map('trim', explode(' - ', $table['title'], 2)) : [];
    @endphp
    <section class="competition-standings-card">
        @if($titleParts !== [])
            <header>
                <strong>{{ $titleParts[0] }}</strong>
                @if(isset($titleParts[1]))<span>{{ $titleParts[1] }}</span>@endif
            </header>
        @endif
        <div class="standings-table-scroll" role="region" aria-label="{{ $table['title'] ?: 'Turnuva' }} puan durumu" tabindex="0">
            <table class="standings-table competition-standings-table">
                <thead><tr>
                    <th class="standings-rank" scope="col">#</th><th class="standings-team" scope="col">Takım</th>
                    <th scope="col">O</th><th class="standing-detail" scope="col">G</th><th class="standing-detail" scope="col">B</th><th class="standing-detail" scope="col">M</th>
                    <th class="standing-detail" scope="col">A</th><th class="standing-detail" scope="col">Y</th><th scope="col">AV</th><th class="standings-points" scope="col">P</th>
                </tr></thead>
                <tbody>
                    @foreach($table['rows'] as $row)
                        @php
                            $providerTeam = $localTeams->get($row['provider_team_id']);
                            $logo = $providerTeam?->logoUrl() ?: $row['team_logo'];
                            $isTurkey = $turkeyProviderTeamId !== null && $row['provider_team_id'] === $turkeyProviderTeamId;
                        @endphp
                        <tr @class(['is-current-team' => $isTurkey]) @if($isTurkey) data-turkey-team="true" @endif @if($row['zone_name']) title="{{ $row['zone_name'] }}" @endif @if($row['zone_color']) style="--standing-zone:{{ $row['zone_color'] }}" @endif>
                            <td class="standings-rank">{{ $row['rank'] }}</td>
                            <th class="standings-team" scope="row">
                                @if($providerTeam)<a class="standings-team-inner standings-team-link" href="{{ $providerTeam->publicUrl() }}">@else<span class="standings-team-inner">@endif
                                    <span class="standings-club-logo">@if($logo)<img src="{{ $logo }}" alt="" loading="lazy" decoding="async" onerror="this.remove()">@endif</span>
                                    <span class="standings-team-name">{{ $row['team_name'] }}</span>
                                @if($providerTeam)</a>@else</span>@endif
                            </th>
                            <td>{{ $row['played'] }}</td><td class="standing-detail">{{ $row['won'] }}</td><td class="standing-detail">{{ $row['drawn'] }}</td><td class="standing-detail">{{ $row['lost'] }}</td>
                            <td class="standing-detail">{{ $row['goals_for'] }}</td><td class="standing-detail">{{ $row['goals_against'] }}</td><td>{{ $row['goal_diff'] > 0 ? '+' : '' }}{{ $row['goal_diff'] }}</td><td class="standings-points">{{ $row['points'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@empty
    <p class="competition-inline-note">Puan durumu şu anda görüntülenemiyor.</p>
@endforelse
