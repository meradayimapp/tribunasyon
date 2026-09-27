@props(['date', 'today', 'status' => 'all', 'competitionSlug' => 'all'])
@php
    $days = collect(range(-3, 3))->map(fn (int $offset) => $date->addDays($offset));
    $queryFor = fn ($target) => array_filter([
        'date' => $target->toDateString(),
        'status' => $status !== 'all' ? $status : null,
        'competition' => $competitionSlug !== 'all' ? $competitionSlug : null,
    ], fn ($value) => $value !== null);
@endphp
<section class="matches-date-picker" aria-label="Maç tarihi seçimi">
    <div class="matches-date-tools">
        <a href="{{ route('matches.index', $queryFor($date->subWeek())) }}" aria-label="Önceki haftaya git">
            <x-ui.icon name="chevron-left" />
            <span>Önceki hafta</span>
        </a>
        <form method="GET" action="{{ route('matches.index') }}">
            @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            @if($competitionSlug !== 'all')<input type="hidden" name="competition" value="{{ $competitionSlug }}">@endif
            <label>
                <x-ui.icon name="calendar" />
                <span class="visually-hidden">Tarih seç</span>
                <input type="date" name="date" value="{{ $date->toDateString() }}" aria-label="Tarih seç">
            </label>
            <button type="submit">Git</button>
        </form>
        <a href="{{ route('matches.index', $queryFor($date->addWeek())) }}" aria-label="Sonraki haftaya git">
            <span>Sonraki hafta</span>
            <x-ui.icon name="chevron-right" />
        </a>
    </div>

    <nav class="matches-date-days" aria-label="Yakın tarihler">
        @foreach($days as $day)
            @php($isToday = $day->isSameDay($today))
            <a
                href="{{ route('matches.index', $queryFor($day)) }}"
                @class(['active' => $day->isSameDay($date), 'is-today' => $isToday])
                @if($day->isSameDay($date)) aria-current="date" @endif
            >
                <span>{{ $isToday ? 'Bugün' : $day->locale('tr')->translatedFormat('D') }}</span>
                <strong>{{ $day->format('d.m') }}</strong>
            </a>
        @endforeach
    </nav>
</section>
