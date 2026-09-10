<?php

namespace App\Services;

use App\Data\SeoData;
use App\Enums\TeamStatus;
use App\Models\FootballMatch;
use App\Models\Post;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeoService
{
    public const DEFAULT_DESCRIPTION = 'Takım topluluklarının sosyal futbol platformu.';

    public function defaults(Request $request, ?string $pageTitle = null): SeoData
    {
        $title = filled($pageTitle) ? $pageTitle.' | '.$this->siteName() : $this->siteName();

        return new SeoData(
            title: $title,
            description: self::DEFAULT_DESCRIPTION,
            canonical: $this->canonical($request->path() === '/' ? '/' : '/'.$request->path()),
            robots: $this->shouldNoindex($request) ? 'noindex, nofollow' : 'index, follow',
        );
    }

    public function post(Post $post): SeoData
    {
        $description = $post->seo_description ?: $this->excerpt($post->body, 160);
        $title = $post->seo_title ?: $this->excerpt($post->body, 65).' | '.$this->siteName();
        $canonical = $this->canonical(route('posts.show', [$post->team, $post], false));
        $image = $this->postImage($post);

        return new SeoData(
            title: $title,
            description: $description,
            canonical: $canonical,
            type: 'article',
            image: $image,
            robots: $post->isIndexable() ? 'index, follow' : 'noindex, nofollow',
            structuredData: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'SocialMediaPosting',
                'headline' => $title,
                'description' => $description,
                'url' => $canonical,
                'datePublished' => $post->published_at?->toIso8601String(),
                'image' => $image,
            ], fn (mixed $value): bool => filled($value)),
        );
    }

    public function team(Team $team, string $page = 'show'): SeoData
    {
        [$title, $description, $route] = match ($page) {
            'fixtures' => [
                "{$team->name} Fikstürü ve Maçları | {$this->siteName()}",
                "{$team->name} fikstürü, yaklaşan maçları ve güncel sonuçları {$this->siteName()}'da.",
                'teams.fixtures',
            ],
            'players' => [
                "{$team->name} Oyuncuları ve Kadrosu | {$this->siteName()}",
                "{$team->name} oyuncuları, güncel kadrosu ve futbolcu profilleri {$this->siteName()}'da.",
                'teams.players',
            ],
            default => [
                "{$team->name} Topluluğu, Paylaşımlar ve Maçlar | {$this->siteName()}",
                "{$team->name} topluluğuna katılın; takım paylaşımlarını, maçlarını ve oyuncularını {$this->siteName()}'da takip edin.",
                'teams.show',
            ],
        };

        $canonical = $this->canonical(route($route, $team, false));
        $image = $this->absolute($team->coverImageUrl() ?: $team->logoUrl());
        $indexable = ! $team->trashed() && $team->status === TeamStatus::Active;

        return new SeoData(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            robots: $indexable ? 'index, follow' : 'noindex, nofollow',
            structuredData: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'SportsTeam',
                'name' => $team->name,
                'url' => $this->canonical(route('teams.show', $team, false)),
                'logo' => $this->absolute($team->logoUrl()),
                'image' => $image,
            ], fn (mixed $value): bool => filled($value)),
        );
    }

    public function match(FootballMatch $match): SeoData
    {
        $home = $match->homeTeam->resolved_name;
        $away = $match->awayTeam->resolved_name;
        $competition = $match->competition->display_name ?: $match->competition->name;
        $date = $match->kickoffInDisplayTimezone()->translatedFormat('d F Y H:i');
        $title = "{$home} - {$away} Maçı | {$this->siteName()}";
        $description = "{$home} - {$away} maçı, {$competition}, {$date}; maç merkezi ve güncel karşılaşma bilgileri.";
        $canonical = $this->canonical(route('matches.show', $match, false));
        $image = $this->absolute($match->homeTeam->logoUrl() ?: $match->awayTeam->logoUrl());

        $event = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => "{$home} - {$away}",
            'url' => $canonical,
            'startDate' => $match->kickoff_at?->toIso8601String(),
            'eventStatus' => $this->eventStatus($match->status),
            'location' => $match->venue_name ? ['@type' => 'Place', 'name' => $match->venue_name] : null,
            'homeTeam' => ['@type' => 'SportsTeam', 'name' => $home],
            'awayTeam' => ['@type' => 'SportsTeam', 'name' => $away],
        ], fn (mixed $value): bool => filled($value));

        return new SeoData(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            robots: $match->isIndexable() ? 'index, follow' : 'noindex, nofollow',
            structuredData: $event,
        );
    }

    public function canonical(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        return $base.'/'.ltrim($path, '/');
    }

    public function absolute(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return $this->canonical($url);
    }

    private function siteName(): string
    {
        return (string) config('app.name', 'Tribünasyon');
    }

    private function excerpt(string $value, int $limit): string
    {
        return Str::limit(Str::squish(strip_tags($value)), $limit, '…');
    }

    private function postImage(Post $post): ?string
    {
        $path = $post->media->first()?->path ?: $post->image_path;

        return $path ? $this->absolute(Storage::disk('public')->url($path)) : null;
    }

    private function eventStatus(string $status): ?string
    {
        return match (strtolower($status)) {
            'scheduled', 'not_started' => 'https://schema.org/EventScheduled',
            'live', 'in_progress' => 'https://schema.org/EventInProgress',
            'finished' => 'https://schema.org/EventCompleted',
            'postponed' => 'https://schema.org/EventPostponed',
            'cancelled', 'canceled', 'abandoned' => 'https://schema.org/EventCancelled',
            default => null,
        };
    }

    private function shouldNoindex(Request $request): bool
    {
        return $request->routeIs(
            'admin.*',
            'moderator.*',
            'profile.*',
            'onboarding.*',
            'login',
            'register',
            'password.*',
            'search.*',
        );
    }
}
