<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Post;
use App\Models\Team;
use Illuminate\Support\Collection;

class CommunitySearchService
{
    public function players(string $query, int $limit = 8): Collection
    {
        if (mb_strlen(trim($query)) < 2) {
            return collect();
        }

        return Player::query()
            ->active()
            ->matching($query)
            ->with('currentTeam:id,name,slug,short_name,logo,primary_color')
            ->ordered()
            ->limit($limit)
            ->get();
    }

    public function teams(string $query, int $limit = 8): Collection
    {
        $pattern = $this->pattern($query);

        return Team::query()
            ->active()
            ->where(function ($builder) use ($pattern): void {
                $builder
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("short_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("slug LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->ordered()
            ->limit($limit)
            ->get();
    }

    public function posts(string $query, int $limit = 12): Collection
    {
        $pattern = $this->pattern($query);

        return Post::query()
            ->select(['id', 'team_id', 'type', 'body', 'image_path', 'status', 'published_at'])
            ->published()
            ->whereHas('team', fn ($teams) => $teams->active())
            ->whereRaw("body LIKE ? ESCAPE '!'", [$pattern])
            ->with(['team:id,name,slug,short_name,logo,primary_color', 'coverMedia'])
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private function pattern(string $query): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($query));

        return "%{$escaped}%";
    }
}
