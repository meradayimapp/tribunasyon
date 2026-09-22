@forelse($shownTables as $table)
    @if($table['title'])<h3 class="standings-group-title">{{ $table['title'] }}</h3>@endif
    <div class="standings-table-scroll" role="region" aria-label="{{ $table['title'] ?: 'Turnuva' }} puan durumu" tabindex="0">
        <table class="standings-table competition-standings-table">
            <thead><tr>
                <th scope="col">Sıra</th><th class="standings-team" scope="col">Takım</th>
                <th scope="col">O</th><th scope="col">G</th><th scope="col">B</th><th scope="col">M</th>
                <th scope="col">A</th><th scope="col">Y</th><th scope="col">AV</th><th scope="col">P</th>
            </tr></thead>
            <tbody>
                @foreach($table['rows'] as $row)
                    @php
                        $providerTeam = $localTeams->get($row['provider_team_id']);
                        $logo = $providerTeam?->logoUrl() ?: $row['team_logo'];
                        $isTurkey = $turkeyProviderTeamId !== null && $row['provider_team_id'] === $turkeyProviderTeamId;
                    @endphp
                    <tr @class(['is-current-team' => $isTurkey]) @if($isTurkey) data-turkey-team="true" @endif @if($row['zone_name']) title="{{ $row['zone_name'] }}" @endif @if($row['zone_color']) style="--standing-zone:{{ $row['zone_color'] }}" @endif>
                        <td>{{ $row['rank'] }}</td>
                        <th class="standings-team" scope="row">
                            @if($providerTeam)<a class="standings-team-inner standings-team-link" href="{{ $providerTeam->publicUrl() }}">@else<span class="standings-team-inner">@endif
                                <span class="standings-club-logo">@if($logo)<img src="{{ $logo }}" alt="" loading="lazy" decoding="async" onerror="this.remove()">@endif</span>
                                <span class="standings-team-name">{{ $row['team_name'] }}</span>
                            @if($providerTeam)</a>@else</span>@endif
                        </th>
                        <td>{{ $row['played'] }}</td><td>{{ $row['won'] }}</td><td>{{ $row['drawn'] }}</td><td>{{ $row['lost'] }}</td>
                        <td>{{ $row['goals_for'] }}</td><td>{{ $row['goals_against'] }}</td><td>{{ $row['goal_diff'] }}</td><td class="standings-points">{{ $row['points'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p class="competition-empty">Puan durumu şu anda görüntülenemiyor.</p>
@endforelse
