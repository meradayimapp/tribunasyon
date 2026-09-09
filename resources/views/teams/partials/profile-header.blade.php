<section class="team-hero team-profile-hero" style="--team-primary:{{ $team->primary_color }}">
    @if($team->cover_image)<img class="team-cover" src="{{ Storage::url($team->cover_image) }}" alt="{{ $team->name }} kapak görseli" loading="eager" decoding="async">@endif
    <div class="team-hero-content">
        <div class="team-identity"><x-team-logo :team="$team" size="xl" /><div><span>Takım topluluğu</span><div class="team-name-line"><h1>{{ $team->name }}</h1><x-organization-badge :team="$team" size="hero" /></div></div></div>
        <livewire:follow-team :team="$team" />
    </div>
</section>

<nav class="team-profile-tabs" aria-label="Takım profili bölümleri">
    <a href="{{ route('teams.show', $team) }}" @class(['active' => $activeTab === 'feed']) @if($activeTab === 'feed') aria-current="page" @endif>Akış</a>
    <a href="{{ route('teams.fixtures', $team) }}" @class(['active' => $activeTab === 'fixtures']) @if($activeTab === 'fixtures') aria-current="page" @endif>Fikstür</a>
    <a href="{{ route('teams.players', $team) }}" @class(['active' => $activeTab === 'players']) @if($activeTab === 'players') aria-current="page" @endif>Oyuncular</a>
</nav>
