<?php

namespace App\Providers;

use App\Contracts\FootballDataService;
use App\Models\Comment;
use App\Models\Post;
use App\Services\MockFootballDataService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FootballDataService::class, MockFootballDataService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap(['post' => Post::class, 'comment' => Comment::class]);
    }
}
