@props(['date', 'matches', 'turkeyProviderTeamId' => null])
@php
    $localDate = \Carbon\CarbonImmutable::parse($date, \App\Models\FootballMatch::DISPLAY_TIMEZONE)->locale('tr');
    $today = \Carbon\CarbonImmutable::now(\App\Models\FootballMatch::DISPLAY_TIMEZONE)->startOfDay();
    $prefix = $localDate->isSameDay($today) ? 'BUGÜN • ' : ($localDate->isSameDay($today->addDay()) ? 'YARIN • ' : '');
    $dateLabel = $prefix.mb_strtoupper($localDate->translatedFormat('d F l'), 'UTF-8');
    $orderedMatches = $matches->sort(fn ($left, $right) =>
        (($right->is_live <=> $left->is_live) ?: ($left->kickoff_at <=> $right->kickoff_at) ?: ($left->id <=> $right->id))
    );
@endphp
<section class="competition-date-group" aria-labelledby="competition-date-{{ $date }}">
    <h3 id="competition-date-{{ $date }}">{{ $dateLabel }}</h3>
    <div class="competition-match-list">
        @foreach($orderedMatches as $match)
            <x-football.competition-match-row :match="$match" :turkey-provider-team-id="$turkeyProviderTeamId" />
        @endforeach
    </div>
</section>
