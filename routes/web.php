<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\Moderator;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', FeedController::class)->name('home');
Route::get('/takimlar', [TeamController::class, 'index'])->name('teams.index');
Route::get('/takim/{team}', [TeamController::class, 'show'])->name('teams.show');
Route::get('/takim/{team}/gonderi/{post}', [PostController::class, 'show'])->scopeBindings()->name('posts.show');
Route::get('/maclar', MatchController::class)->name('matches.index');
Route::get('/ara', SearchController::class)->name('search.index');
Route::get('/profil/{user}', [ProfileController::class, 'show'])->name('profile.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/kayit', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/kayit', [RegisteredUserController::class, 'store']);
    Route::get('/giris', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/giris', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/sifremi-unuttum', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/sifremi-unuttum', [PasswordResetLinkController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/sifre-sifirla/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/sifre-sifirla', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/cikis', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/profil-duzenle', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil-duzenle', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil-sifre', [ProfileController::class, 'password'])->name('profile.password');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::resource('teams', Admin\TeamController::class);
    Route::post('teams/{team}/restore', [Admin\TeamController::class, 'restore'])->name('teams.restore');
    Route::resource('users', Admin\UserController::class)->only(['index', 'edit', 'update']);
    Route::resource('moderators', Admin\ModeratorController::class)->only(['index', 'edit', 'update'])->parameters(['moderators' => 'user']);
    Route::resource('posts', Admin\PostController::class)->only(['index', 'destroy']);
    Route::post('posts/{post}/restore', [Admin\PostController::class, 'restore'])->name('posts.restore');
    Route::resource('comments', Admin\CommentController::class)->only(['index', 'destroy']);
    Route::post('comments/{comment}/restore', [Admin\CommentController::class, 'restore'])->name('comments.restore');
});

Route::prefix('moderator')->name('moderator.')->middleware(['auth', 'role:moderator'])->group(function (): void {
    Route::get('/', Moderator\DashboardController::class)->name('dashboard');
    Route::resource('posts', Moderator\PostController::class)->except('show');
    Route::resource('comments', Moderator\CommentController::class)->only(['index', 'destroy']);
});
