@if($matches !== [])
    <section
        class="today-scores-ribbon"
        aria-label="Bugünün skorları"
        data-polling="{{ $pollingEnabled ? 'on' : 'off' }}"
        data-match-ids="{{ implode(',', array_column($matches, 'id')) }}"
        x-data="todayScoresRibbon({{ Js::from(route('matches.today.state')) }}, {{ Js::from($matches) }}, {{ Js::from($pollingEnabled) }})"
        @visibilitychange.document="visibilityChanged()"
    >
        <div class="today-scores-ribbon-inner">
            <a class="today-scores-label" href="{{ route('matches.index') }}">
                <span>Bugünün</span>
                <strong>Skorları</strong>
            </a>
            <div class="today-scores-track" role="list">
                <template x-for="match in matches" :key="match.id">
                    <a
                        class="today-score-card"
                        :class="{ 'is-live': match.is_live, 'is-finished': match.is_finished }"
                        :href="match.url"
                        :aria-label="`${match.home_team.name} - ${match.away_team.name} maçını aç`"
                        :data-match-id="match.id"
                        role="listitem"
                    >
                        <span class="today-score-team">
                            <img x-show="match.home_team.logo" :src="match.home_team.logo" alt="" loading="lazy" decoding="async" x-on:error="$el.remove()">
                            <b x-text="shortName(match.home_team.short_name)"></b>
                        </span>
                        <span class="today-score-state">
                            <strong x-text="centerText(match)"></strong>
                            <small x-text="cardStatus(match)"></small>
                        </span>
                        <span class="today-score-team today-score-team-away">
                            <b x-text="shortName(match.away_team.short_name)"></b>
                            <img x-show="match.away_team.logo" :src="match.away_team.logo" alt="" loading="lazy" decoding="async" x-on:error="$el.remove()">
                        </span>
                    </a>
                </template>
            </div>
        </div>
    </section>
@endif
