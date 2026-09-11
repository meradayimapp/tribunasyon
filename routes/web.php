<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TeamOnboardingController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FootballTeamController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\Moderator;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoInfrastructureController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', FeedController::class)->name('home');
Route::get('/sitemap.xml', [SeoInfrastructureController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoInfrastructureController::class, 'robots'])->name('seo.robots');
Route::get('/takimlar', [TeamController::class, 'index'])->name('teams.index');
Route::get('/takim/{team}', [TeamController::class, 'show'])->name('teams.show');
Route::get('/takim/{team}/fikstur', [TeamController::class, 'fixtures'])->name('teams.fixtures');
Route::get('/takim/{team}/oyuncular', [TeamController::class, 'players'])->name('teams.players');
Route::get('/takim/{team}/gonderi/{post}', [PostController::class, 'show'])->scopeBindings()->name('posts.show');
Route::get('/maclar', [MatchController::class, 'index'])->name('matches.index');
Route::get('/maclar/{footballMatch}', [MatchController::class, 'show'])->whereNumber('footballMatch')->name('matches.show');
Route::get('/maclar/{footballMatch}/state', [MatchController::class, 'state'])->whereNumber('footballMatch')->name('matches.state');
Route::get('/futbol/takimlar/{footballTeam}', FootballTeamController::class)->whereNumber('footballTeam')->name('football-teams.show');
Route::get('/oyuncular', [PlayerController::class, 'index'])->name('players.index');
Route::get('/oyuncu/{player}', [PlayerController::class, 'show'])->name('players.show');
Route::get('/ara', SearchController::class)->name('search.index');
Route::get('/profil/{user}', [ProfileController::class, 'show'])->name('profile.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/kayit', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/kayit', [RegisteredUserController::class, 'store']);
    Route::get('/giris', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/giris', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/auth/google', [GoogleOAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->name('auth.google.callback');
    Route::get('/sifremi-unuttum', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/sifremi-unuttum', [PasswordResetLinkController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/sifre-sifirla/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/sifre-sifirla', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/cikis', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/takimlarini-sec', [TeamOnboardingController::class, 'edit'])->name('onboarding.teams.edit');
    Route::put('/takimlarini-sec', [TeamOnboardingController::class, 'update'])->name('onboarding.teams.update');
    Route::post('/takimlarini-sec/gec', [TeamOnboardingController::class, 'skip'])->name('onboarding.teams.skip');
    Route::get('/profil-duzenle', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil-duzenle', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil-sifre', [ProfileController::class, 'password'])->name('profile.password');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('settings', [Admin\SiteSettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [Admin\SiteSettingController::class, 'update'])->name('settings.update');
    Route::resource('teams', Admin\TeamController::class);
    Route::post('teams/{team}/restore', [Admin\TeamController::class, 'restore'])->name('teams.restore');
    Route::get('players/import', [Admin\PlayerSquadImportController::class, 'create'])->name('players.import.create');
    Route::post('players/import/preview', [Admin\PlayerSquadImportController::class, 'preview'])->name('players.import.preview');
    Route::post('players/import/apply', [Admin\PlayerSquadImportController::class, 'apply'])->name('players.import.apply');
    Route::resource('players', Admin\PlayerController::class)->except('show');
    Route::post('players/{player}/restore', [Admin\PlayerController::class, 'restore'])->name('players.restore');
    Route::resource('organizations', Admin\OrganizationController::class)->except('show');
    Route::resource('users', Admin\UserController::class)->only(['index', 'edit', 'update']);
    Route::resource('moderators', Admin\ModeratorController::class)->only(['index', 'edit', 'update'])->parameters(['moderators' => 'user']);
    Route::resource('posts', Admin\PostController::class)->only(['index', 'edit', 'update', 'destroy']);
    Route::post('posts/{post}/restore', [Admin\PostController::class, 'restore'])->name('posts.restore');
    Route::resource('comments', Admin\CommentController::class)->only(['index', 'destroy']);
    Route::post('comments/{comment}/restore', [Admin\CommentController::class, 'restore'])->name('comments.restore');
});

Route::prefix('moderator')->name('moderator.')->middleware(['auth', 'role:moderator'])->group(function (): void {
    Route::get('/', Moderator\DashboardController::class)->name('dashboard');
    Route::resource('posts', Moderator\PostController::class)->except('show');
    Route::resource('comments', Moderator\CommentController::class)->only(['index', 'destroy']);
});
