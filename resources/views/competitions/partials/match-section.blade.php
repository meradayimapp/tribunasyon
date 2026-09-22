<section class="competition-section" aria-labelledby="{{ $sectionId }}">
    <div class="competition-section-heading">
        <h2 id="{{ $sectionId }}">{{ $title }}</h2>
        @isset($actionUrl)<a href="{{ $actionUrl }}">{{ $actionLabel ?? 'Tümünü gör' }} <x-ui.icon name="chevron-right" /></a>@endisset
    </div>
    <div class="competition-match-list">
        @forelse($sectionMatches as $match)
            @include('competitions.partials.match-row', ['match' => $match])
        @empty
            <p class="competition-empty">{{ $empty ?? 'Maç bulunmuyor.' }}</p>
        @endforelse
    </div>
</section>
