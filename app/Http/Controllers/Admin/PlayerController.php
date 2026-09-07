<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlayerStatus;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Team;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Throwable;

class PlayerController extends Controller
{
    public function index(Request $request): View
    {
        $players = Player::query()
            ->withTrashed()
            ->with('currentTeam')
            ->when($request->string('q')->isNotEmpty(), fn ($query) => $query->matching($request->string('q')->toString()))
            ->when($request->filled('team'), fn ($query) => $query->where('current_team_id', $request->integer('team')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('admin.players.index', [
            'players' => $players,
            'teams' => Team::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Player::class);

        return view('admin.players.form', [
            'player' => new Player,
            'teams' => Team::active()->ordered()->get(),
        ]);
    }

    public function store(Request $request, MediaStorageService $media): RedirectResponse
    {
        $this->authorize('create', Player::class);
        $data = $this->validated($request);
        $storedPaths = [];

        try {
            if ($request->hasFile('photo_file')) {
                $data['photo_path'] = $media->store($request->file('photo_file'), 'players/photos');
                $storedPaths[] = $data['photo_path'];
            }
            if ($request->hasFile('cover_file')) {
                $data['cover_image_path'] = $media->store($request->file('cover_file'), 'players/covers');
                $storedPaths[] = $data['cover_image_path'];
            }

            unset($data['photo_file'], $data['cover_file'], $data['remove_photo'], $data['remove_cover']);
            DB::transaction(fn () => Player::create($data));
        } catch (Throwable $exception) {
            $media->delete($storedPaths);
            throw $exception;
        }

        return redirect()->route('admin.players.index')->with('success', 'Oyuncu oluşturuldu.');
    }

    public function edit(Player $player): View
    {
        $this->authorize('update', $player);
        $teams = Team::active()->ordered()->get();
        if ($player->currentTeam && ! $teams->contains('id', $player->current_team_id)) {
            $teams->prepend($player->currentTeam);
        }

        return view('admin.players.form', compact('player', 'teams'));
    }

    public function update(Request $request, Player $player, MediaStorageService $media): RedirectResponse
    {
        $this->authorize('update', $player);
        $data = $this->validated($request, $player);
        $oldPhoto = $player->photo_path;
        $oldCover = $player->cover_image_path;
        $storedPaths = [];

        try {
            if ($request->hasFile('photo_file')) {
                $data['photo_path'] = $media->store($request->file('photo_file'), 'players/photos');
                $storedPaths[] = $data['photo_path'];
            } elseif ($request->boolean('remove_photo')) {
                $data['photo_path'] = null;
            }
            if ($request->hasFile('cover_file')) {
                $data['cover_image_path'] = $media->store($request->file('cover_file'), 'players/covers');
                $storedPaths[] = $data['cover_image_path'];
            } elseif ($request->boolean('remove_cover')) {
                $data['cover_image_path'] = null;
            }

            unset($data['photo_file'], $data['cover_file'], $data['remove_photo'], $data['remove_cover']);
            DB::transaction(fn () => $player->update($data));
        } catch (Throwable $exception) {
            $media->delete($storedPaths);
            throw $exception;
        }

        $player->refresh();
        if ($oldPhoto !== $player->photo_path) {
            $this->deleteIfUnused($media, $oldPhoto, 'photo_path', 'players/photos/');
        }
        if ($oldCover !== $player->cover_image_path) {
            $this->deleteIfUnused($media, $oldCover, 'cover_image_path', 'players/covers/');
        }

        return redirect()->route('admin.players.index')->with('success', 'Oyuncu güncellendi.');
    }

    public function destroy(Player $player): RedirectResponse
    {
        $this->authorize('delete', $player);
        $player->delete();

        return back()->with('success', 'Oyuncu silindi.');
    }

    public function restore(int $player): RedirectResponse
    {
        $record = Player::withTrashed()->findOrFail($player);
        $this->authorize('restore', $record);
        $record->restore();

        return back()->with('success', 'Oyuncu geri alındı.');
    }

    private function validated(Request $request, ?Player $player = null): array
    {
        if (! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->string('name')->toString())]);
        }
        $request->merge([
            'national_team_code' => $request->filled('national_team_code') ? Str::upper($request->string('national_team_code')->toString()) : null,
            'market_value_currency' => $request->filled('market_value_currency') ? Str::upper($request->string('market_value_currency')->toString()) : null,
        ]);

        $teamIds = Team::active()->pluck('id');
        if ($player?->current_team_id) {
            $teamIds->push($player->current_team_id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:140', Rule::unique('players')->ignore($player)],
            'position' => ['nullable', 'string', 'max:80'],
            'shirt_number' => ['nullable', 'integer', 'min:0', 'max:99'],
            'current_team_id' => ['nullable', 'integer', Rule::in($teamIds->unique()->all())],
            'national_team_name' => ['nullable', 'string', 'max:100'],
            'national_team_code' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'market_value_amount' => ['nullable', 'integer', 'min:0', 'required_with:market_value_currency'],
            'market_value_currency' => ['nullable', 'regex:/^[A-Z]{3}$/', 'required_with:market_value_amount'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::enum(PlayerStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'photo_file' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
            'cover_file' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
            'remove_photo' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);
    }

    private function deleteIfUnused(MediaStorageService $media, ?string $path, string $column, string $directory): void
    {
        if ($path && str_starts_with($path, $directory) && ! Player::withTrashed()->where($column, $path)->exists()) {
            $media->delete($path);
        }
    }
}
