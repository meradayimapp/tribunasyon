<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeamStatus;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('admin.teams.index', ['teams' => Team::withTrashed()->withCount(['followers', 'posts', 'moderators'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.teams.form', ['team' => new Team]);
    }

    public function edit(Team $team): View
    {
        return view('admin.teams.form', compact('team'));
    }

    public function show(Team $team): View
    {
        $team->load(['moderators', 'posts' => fn ($query) => $query->with('creator')->withCount(['likes', 'comments'])->latest()->limit(10)])->loadCount('followers');

        return view('admin.teams.show', compact('team'));
    }

    public function store(Request $request, MediaStorageService $media): RedirectResponse
    {
        Team::create($this->validated($request, $media));

        return redirect()->route('admin.teams.index')->with('success', 'Takım oluşturuldu.');
    }

    public function update(Request $request, Team $team, MediaStorageService $media): RedirectResponse
    {
        $team->update($this->validated($request, $media, $team));

        return redirect()->route('admin.teams.index')->with('success', 'Takım güncellendi.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return back()->with('success', 'Takım silindi.');
    }

    public function restore(int $team): RedirectResponse
    {
        Team::withTrashed()->findOrFail($team)->restore();

        return back()->with('success', 'Takım geri alındı.');
    }

    private function validated(Request $request, MediaStorageService $media, ?Team $team = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('teams')->ignore($team)],
            'short_name' => ['required', 'string', 'max:12'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::enum(TeamStatus::class)],
            'logo_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'cover_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        if ($request->hasFile('logo_file')) {
            $data['logo'] = $media->replace($team?->logo, $request->file('logo_file'), 'teams/logos');
        }
        if ($request->hasFile('cover_file')) {
            $data['cover_image'] = $media->replace($team?->cover_image, $request->file('cover_file'), 'teams/covers');
        }
        unset($data['logo_file'], $data['cover_file']);

        return $data;
    }
}
