@foreach($tables as $table)
    @if($tables->count() > 1 && $table['title'])
        <h3 class="standings-group-title">{{ $table['title'] }}</h3>
    @endif
    <div class="standings-table-scroll" role="region" aria-label="{{ $table['title'] ?: 'Lig' }} puan durumu" tabindex="0">
        <table class="standings-table">
            <thead><tr>
                <th class="standings-rank" scope="col">#</th><th class="standings-team" scope="col">Takım</th>
                <th scope="col"><abbr title="Oynanan">O</abbr></th><th scope="col"><abbr title="Galibiyet">G</abbr></th>
                <th scope="col"><abbr title="Beraberlik">B</abbr></th><th scope="col"><abbr title="Mağlubiyet">M</abbr></th>
                <th scope="col"><abbr title="Atılan gol">AG</abbr></th><th scope="col"><abbr title="Yenilen gol">YG</abbr></th>
                <th scope="col"><abbr title="Averaj">AV</abbr></th><th class="standings-points" scope="col"><abbr title="Puan">P</abbr></th>
                <th class="standings-form-heading" scope="col">Son 5</th>
            </tr></thead>
            <tbody>
                @foreach($table['rows'] as $row)
                    @php
                        $isCurrentTeam = in_array($row['provider_team_id'], $currentProviderTeamIds, true);
                        $localTeam = $localTeams->get($row['provider_team_id']);
                        $logo = ($localTeam['logo'] ?? null) ?: $row['team_logo'];
                    @endphp
                    <tr @class(['is-current-team' => $isCurrentTeam]) @if($isCurrentTeam) data-current-team="true" @endif @if($row['zone_name']) title="{{ $row['zone_name'] }}" @endif @if($row['zone_color']) style="--standing-zone:{{ $row['zone_color'] }}" @endif>
                        <td class="standings-rank">{{ $row['rank'] }}</td>
                        <th class="standings-team" scope="row">
                            @if($localTeam)
                                <a class="standings-team-inner standings-team-link" href="{{ $localTeam['url'] }}">
                                    <span class="standings-club-logo">@if($logo)<img src="{{ $logo }}" alt="" loading="lazy" decoding="async" onerror="this.remove()">@endif</span>
                                    <span class="standings-team-name">{{ $row['team_name'] }}</span>
                                </a>
                            @else
                                <span class="standings-team-inner">
                                    <span class="standings-club-logo">@if($logo)<img src="{{ $logo }}" alt="" loading="lazy" decoding="async" onerror="this.remove()">@endif</span>
                                    <span class="standings-team-name">{{ $row['team_name'] }}</span>
                                </span>
                            @endif
                        </th>
                        <td>{{ $row['played'] }}</td><td>{{ $row['won'] }}</td><td>{{ $row['drawn'] }}</td>
                        <td>{{ $row['lost'] }}</td><td>{{ $row['goals_for'] }}</td><td>{{ $row['goals_against'] }}</td>
                        <td>{{ $row['goal_diff'] }}</td><td class="standings-points">{{ $row['points'] }}</td>
                        <td class="standings-form">
                            @if($row['form'])
                                @foreach(str_split($row['form']) as $result)
                                    @if($result === 'W')<span class="form-result form-win" title="Galibiyet" aria-label="Galibiyet"><x-ui.icon name="check" /></span>
                                    @elseif($result === 'D')<span class="form-result form-draw" title="Beraberlik" aria-label="Beraberlik">−</span>
                                    @else<span class="form-result form-loss" title="Mağlubiyet" aria-label="Mağlubiyet"><x-ui.icon name="close" /></span>@endif
                                @endforeach
                            @else<span class="standings-no-form">—</span>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
