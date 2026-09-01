<?php

namespace App\Providers;

use App\Contracts\FootballDataService;
use App\Models\Comment;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Observers\PostObserver;
use App\Services\MockFootballDataService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
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
        Post::observe(PostObserver::class);
        Paginator::useBootstrapFive();
        View::composer('layouts.app', fn ($view) => $view->with('siteSettings', SiteSetting::current()));
    }
}
