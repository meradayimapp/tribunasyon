<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeamStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Team;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('admin.teams.index', ['teams' => Team::withTrashed()->withCount(['followers', 'posts', 'moderators'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.teams.form', [
            'team' => new Team,
            'organizations' => Organization::active()->orderBy('name')->get(),
        ]);
    }

    public function edit(Team $team): View
    {
        $team->load('organization');
        $organizations = Organization::active()->orderBy('name')->get();

        if ($team->organization && ! $organizations->contains('id', $team->organization->id)) {
            $organizations->prepend($team->organization);
        }

        return view('admin.teams.form', compact('team', 'organizations'));
    }

    public function show(Team $team): View
    {
        $team->load([
            'organization',
            'moderators',
            'posts' => fn ($query) => $query->with('creator')->withCount(['likes', 'comments'])->latest()->limit(10),
        ])->loadCount('followers');

        return view('admin.teams.show', compact('team'));
    }

    public function store(Request $request, MediaStorageService $media): RedirectResponse
    {
        $data = $this->validated($request);
        $storedPaths = [];

        try {
            if ($request->hasFile('logo_file')) {
                $data['logo'] = $media->store($request->file('logo_file'), 'teams/logos');
                $storedPaths[] = $data['logo'];
            }

            if ($request->hasFile('cover_file')) {
                $data['cover_image'] = $media->store($request->file('cover_file'), 'teams/covers');
                $storedPaths[] = $data['cover_image'];
            }

            unset($data['logo_file'], $data['cover_file'], $data['remove_logo']);

            DB::transaction(fn () => Team::create($data));
        } catch (Throwable $exception) {
            $media->delete($storedPaths);

            throw $exception;
        }

        return redirect()->route('admin.teams.index')->with('success', 'Takım oluşturuldu.');
    }

    public function update(Request $request, Team $team, MediaStorageService $media): RedirectResponse
    {
        $data = $this->validated($request, $team);
        $oldLogo = $team->logo;
        $oldCover = $team->cover_image;
        $storedPaths = [];

        try {
            if ($request->hasFile('logo_file')) {
                $data['logo'] = $media->store($request->file('logo_file'), 'teams/logos');
                $storedPaths[] = $data['logo'];
            } elseif ($request->boolean('remove_logo')) {
                $data['logo'] = null;
            }

            if ($request->hasFile('cover_file')) {
                $data['cover_image'] = $media->store($request->file('cover_file'), 'teams/covers');
                $storedPaths[] = $data['cover_image'];
            }

            unset($data['logo_file'], $data['cover_file'], $data['remove_logo']);

            DB::transaction(fn () => $team->update($data));
        } catch (Throwable $exception) {
            $media->delete($storedPaths);

            throw $exception;
        }

        $team->refresh();

        if ($oldLogo !== $team->logo) {
            $this->deleteTeamMediaIfUnused($media, $oldLogo, 'logo', 'teams/logos/');
        }

        if ($oldCover !== $team->cover_image) {
            $this->deleteTeamMediaIfUnused($media, $oldCover, 'cover_image', 'teams/covers/');
        }

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

    private function validated(Request $request, ?Team $team = null): array
    {
        if (! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->string('name')->toString())]);
        }

        $allowedOrganizationIds = Organization::active()->pluck('id');

        if ($team?->organization_id) {
            $allowedOrganizationIds->push($team->organization_id);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('teams')->ignore($team)],
            'short_name' => ['required', 'string', 'max:12'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::enum(TeamStatus::class)],
            'organization_id' => ['nullable', 'integer', Rule::in($allowedOrganizationIds->unique()->all())],
            'logo_file' => ['nullable', 'file', 'image', 'mimes:png,webp', 'extensions:png,webp', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
            'cover_file' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'extensions:jpeg,jpg,png,webp', 'max:5120'],
        ]);
        $data['organization_id'] = $data['organization_id'] ?? null;

        return $data;
    }

    private function deleteTeamMediaIfUnused(
        MediaStorageService $media,
        ?string $path,
        string $column,
        string $managedDirectory,
    ): void {
        if ($path && str_starts_with($path, $managedDirectory) && ! Team::withTrashed()->where($column, $path)->exists()) {
            $media->delete($path);
        }
    }
}
