<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Post;
use App\Models\Team;
use App\Services\SeoService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoInfrastructureController extends Controller
{
    public function sitemap(SeoService $seoService): StreamedResponse
    {
        return response()->stream(function () use ($seoService): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

            $this->writeUrl($seoService->canonical('/'));

            Team::query()
                ->active()
                ->select(['id', 'slug', 'updated_at'])
                ->lazyById(500)
                ->each(function (Team $team) use ($seoService): void {
                    $lastModified = $team->updated_at?->toAtomString();

                    foreach (['teams.show', 'teams.fixtures', 'teams.players'] as $route) {
                        $this->writeUrl(
                            $seoService->canonical(route($route, $team, false)),
                            $lastModified,
                        );
                    }
                });

            Post::query()
                ->indexable()
                ->select(['id', 'team_id', 'updated_at'])
                ->with('team:id,slug')
                ->lazyById(500)
                ->each(fn (Post $post) => $this->writeUrl(
                    $seoService->canonical(route('posts.show', [$post->team, $post], false)),
                    $post->updated_at?->toAtomString(),
                ));

            FootballMatch::query()
                ->indexable()
                ->select(['id', 'updated_at'])
                ->lazyById(500)
                ->each(fn (FootballMatch $match) => $this->writeUrl(
                    $seoService->canonical(route('matches.show', $match, false)),
                    $match->updated_at?->toAtomString(),
                ));

            echo '</urlset>'.PHP_EOL;
        }, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(SeoService $seoService): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /moderator/',
            'Disallow: /profil-duzenle',
            'Disallow: /profil-sifre',
            'Disallow: /takimlarini-sec',
            'Disallow: /giris',
            'Disallow: /kayit',
            'Disallow: /sifremi-unuttum',
            'Disallow: /sifre-sifirla/',
            'Disallow: /ara',
            '',
            'Sitemap: '.$seoService->canonical('/sitemap.xml'),
            '',
        ];

        return response(implode(PHP_EOL, $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function writeUrl(string $location, ?string $lastModified = null): void
    {
        echo '  <url><loc>'.$this->xml($location).'</loc>';

        if ($lastModified !== null) {
            echo '<lastmod>'.$this->xml($lastModified).'</lastmod>';
        }

        echo '</url>'.PHP_EOL;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
