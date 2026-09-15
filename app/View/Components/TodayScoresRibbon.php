<?php

namespace App\View\Components;

use App\Contracts\FootballDataService;
use App\Models\FootballMatch;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;

class TodayScoresRibbon extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $matches;

    public bool $pollingEnabled;

    public function __construct(FootballDataService $service)
    {
        $matches = $service->matchesForDate(Carbon::today(FootballMatch::DISPLAY_TIMEZONE));

        $this->matches = $service->scoreRibbonPayload($matches);
        $this->pollingEnabled = ! request()->routeIs('matches.show') && $matches->contains('is_live', true);
    }

    public function render(): View|Closure|string
    {
        return view('components.today-scores-ribbon');
    }
}
